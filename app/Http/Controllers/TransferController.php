<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use App\Utils\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TransferController extends Controller
{

    public function bulkTransfer(Request $request, Utils $utils)
    {
        Log::info("########## Validating File #########");

        $request->validate([
            "file" => 'required|file|mimes:csv,xlsx'
        ],[
            "fileName.mimes" => "File should be a csv or excel file"
        ]);
        Log::info("########## File Validated #########");

        if($request->has("file")){
            $accounts=[];
            $allAccounts = Excel::toArray(new \stdClass(), $request->file("file"));
            foreach ($allAccounts as $accounts){
                foreach ($accounts as $account){
                    echo $account[0] . " " . $account[1] . " " . $account[2]. "<br />";
                    try {
                        Log::info("########## Validating Customer #########");

                        $user = $utils->validateUser($account[2]);
                        if (isset($user->Message) && !empty($user)){
                            Log::info("########## Customer Not Validated Successfully. #########");
                            return $utils->message("success", $user->Message, 404);
                        }

                    } catch (\GuzzleHttp\Exception\ClientException $e) {
                        Log::error("########## ". $e->getMessage() ." #########");
                        return $utils->message("error", $e->getMessage(), 400);
                    }
                    }
            }
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function singleTransfer(Request $request, Utils $utils)
    {
        Log::info("########## Validating Input #########");
        $request->validate([
            "account_number" => "required|string|max:10|min:10",
            "amount" => "required|int",
            "narration" => "required|string",
        ]);
        $request_narration = $request->get("narration");
        Log::info("########## Inputs Validated #########");

        Log::info("########## User Data Inputs #########", $request->all());
        $sink_account = $request->get("account_number");
        $source_account = Account::where("user_id",  auth('sanctum')->user()->id)->value("account_number");
        $amount = $request->get("amount");
        $bankCode = $request->get("bank_code");
            try {
                Log::info("########## Validating Customer #########");

                $user = $utils->validateIntraBankUser($source_account);
                if (isset($user->Message) && !empty($user)){
                    Log::info("########## Customer Not Validated Successfully. #########");
                    return $utils->message("error", $user->Message, 400);
                }

                Log::info("########## Customer Validation Response. #########", json_decode(json_encode($user), true));

                if($user->AvailableBalance < $amount){
                    Log::info("########## Available Balance is less than amount requested. #########");
                    return $utils->message("error", "Insufficient Funds", 400);
                }

                try {
                    return  DB::transaction(function () use ($bankCode, $user, $amount, $utils, $sink_account, $source_account, $request_narration)  {
                        try {

                            $milliseconds = substr(floor(microtime(true) * 1000), 5);
                            $tx_ref = "Uzu_" . $milliseconds;
                            $client = new \GuzzleHttp\Client();
                            $narrationSourceAccount = "***" . substr($source_account, 3);
                            $narrationSinkAccount = "***" . substr($sink_account, 3);
                            $narration = ($request_narration) ? $request_narration : "Trf from " . $narrationSourceAccount. " to ". $narrationSinkAccount;

                            if(Transfer::where("transaction_id", $tx_ref)->exists())
                                return $utils->message("error", "Network Error. Please Try Again.", 400);

                            if ($source_account === $sink_account)
                                return $utils->message("error", "Source and Destination Account are the same", 400);

                            Log::info("########## Saving data before sending for transfer #########");
                            $transaction = new Transfer();
                            $transaction->currency_code = 1;
                            $transaction->intrabank = 1;
                            $transaction->minor_amount = $amount;
                            $transaction->minor_fee_amount = 0.0;
                            $transaction->minor_vat_amount = 0.0;
                            $transaction->name_enquiry_reference = "No Reference";
                            $transaction->narration = $narration;
                            $transaction->Response_code = 0;
                            $transaction->sink_account_name = $user->Name;
                            $transaction->sink_account_number = $sink_account;
                            $transaction->source_account_provider_name = Account::where("user_id",  auth('sanctum')->user()->id)->value("account_name");
                            $transaction->sink_account_provider_code = $bankCode ?? 892;
                            $transaction->source_account_provider_code = "090453";
                            $transaction->status = "Pending";
                            $transaction->transaction_id = $tx_ref;
                            $transaction->transaction_status = "No Status";
                            $transaction->transaction_type = "Single";
                            $transaction->user_id = auth('sanctum')->user()->id;
                            $transaction->account_id =Account::where("user_id",  auth('sanctum')->user()->id)->value("id");
                            $transaction->save();

                            if (!$transaction){
                                Log::error("########## Data Not Saved. #########");
                                return $utils->message("error", "Data Not Saved", 400);

                            }
                            $transaction = json_decode(json_encode($transaction), true);
                            Log::info("########## Data Saved Successfully. #########", $transaction);

                            Log::info("########## Sending for transfer #########");
                            $response = $client->request('POST', 'https://staging.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/LocalFundsTransfer', [
                                'form_params' => [
                                    "Amount" => $amount,
                                    "FromAccountNumber" => $source_account,
                                    "ToAccountNumber" => $sink_account,
                                    "RetrievalReference" => $tx_ref,
                                    "Narration" => $narration,
                                    "AuthenticationKey" => env("BANK_ONE_AUTH_TOKEN")
                                ],
                                'headers' => [
                                    'Accept'     => 'application/json',
                                ]
                            ]);
                            $response = json_decode($response->getBody()->getContents());
                            if ($response->IsSuccessful){
                                Log::info("########## Response from transfer #########", json_decode(json_encode($response), true));
                                Log::info("########## Saving Response to database #########");
                                $transfer = Transfer::where("transaction_id", $tx_ref)->update(
                                    [
                                        "status" => "Completed",
                                        "response_code" => $response->ResponseCode,
                                        "transaction_id" => $response->Reference,
                                    ]
                                );
                                if ($transfer){
                                    Log::info("########## Response Saved Successfully. #########");
                                    return $utils->message("success", $response, 200);
                                }

                            }else{
                                Log::error("########## Response Not Saved #########");
                                return $utils->message("error",  $response, 400);

                            }

                        } catch (\GuzzleHttp\Exception\ClientException $e) {
                            Log::error("########## Error : ". $e->getMessage() ." #########");
                            return $utils->message("error",  "Network Error. Please Try Again", 400);
                        }
                    });
                } catch (\Throwable $e) {
                    Log::error("########## Error : ". $e->getMessage() ." #########");
                    return $utils->message("error", "Network Error. Please Try Again", 400);
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                Log::error("########## Error : ". $e->getMessage() ." #########");
                return $utils->message("error",  "Network Error. Please Try Again", 400);
            }

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Transfer $transfer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Transfer $transfer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Transfer $transfer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transfer $transfer)
    {
        //
    }
}
