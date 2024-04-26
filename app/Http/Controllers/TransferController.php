<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransferResource;
use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use App\Utils\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class TransferController extends Controller
{

    public function getDashboardData(Request $request, Utils $utils)
    {
       if (!auth('sanctum')->check())
           return $utils->message("success", "Unauthorized Access", 401);

        $user_id = auth('sanctum')->user()->id;

        $account = Account::where("user_id", $user_id)->value("account_number");

        try {
            $response =  Http::get("https://staging.mybankone.com/BankOneWebAPI/api/Account/GetTransactions/2?",[
                "authtoken" => env("BANK_ONE_AUTH_TOKEN"),
                "accountNumber" => $account,
                "numberOfItems" => 3
            ]);
            $data = $response->object();
             $res = TransactionResource::collection($data->Message);
            return $utils->message("success", $res, 200);

        }catch (\Exception $exception){
            Log::error($exception->getMessage());
        }

    }

    public function getIntraTransfers(Request $request, Utils $utils)
    {
        $request->validate([
            "transfer_type" => "required",
        ]);
        try {
            $transfers = Transfer::where("transfer_type", $request->get("transfer_type"))->orderBy("created_at", "DESC")->limit(300)->get();
            return $utils->message("success", TransferResource::collection($transfers), 200);
        }catch (\Exception $e){
            $data = [
                "error" => $e->getMessage()
            ];
            Log::info("####### Error getting customer transfer ######", $data);
        }

    }
    public function getInterTransfers(Request $request, Utils $utils)
    {
        try {
            $transfers = Transfer::where("transfer_type", "inter")->orderBy("created_at", "DESC")->limit(300)->get();
            return $utils->message("success", TransferResource::collection($transfers), 200);
        }catch (\Exception $e){
            $data = [
                "error" => $e->getMessage()
            ];
            Log::info("####### Error getting customer transfer ######", $data);
        }

    }
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
            "account_number" => "required|string|digits:10",
            "amount" => "required|int",
            "narration" => "required|string",
            "transfer_type" => "string|max:5",
            "sink_bank_code" => "sometimes|int"
        ]);

        if(!auth('sanctum')->check())
            return $utils->message("success", "Unauthorized Access", 401);

        $user_id = auth('sanctum')->user()->id;

        $request_narration = $request->get("narration");
        $transfer_type = $request->get("transfer_type");
        Log::info("########## Inputs Validated #########");

        Log::info("########## User Data Inputs #########", $request->all());
        $sink_account = $request->get("account_number");
        $source_account = Account::where("user_id",  auth('sanctum')->user()->id)->value("account_number");
        $amount = $request->get("amount") * 100;
        $bankCode = $request->get("bank_code");
        $milliseconds = substr(floor(microtime(true) * 1000), 5);
        $tx_ref = "Uzu_" . $milliseconds;

            try {
                Log::info("########## Validating Customer #########");

                $user = $utils->validateIntraBankUser($source_account);
                if (isset($user->Message) && !empty($user)){
                    Log::info("########## Customer Not Validated Successfully. #########");
                    return $utils->message("error", $user->Message, 400);
                }
                $data = json_decode(json_encode($user), true);
                $sinkName = $data["Name"];
                Log::info("########## Customer Validation Response. #########", $data);

                if($user->AvailableBalance < $amount){
                    Log::info("########## Available Balance is less than amount requested. #########");
                    return $utils->message("error", "Insufficient Funds", 400);
                }

                try {
                    return  DB::transaction(function () use ($user_id, $sinkName, $transfer_type, $tx_ref, $bankCode, $user, $amount, $utils, $sink_account, $source_account, $request_narration)  {
                        try {
                            $sourceName = Account::where("user_id",  auth('sanctum')->user()->id)->value("account_name");
                            $client = new \GuzzleHttp\Client();
                            $narrationSourceAccount = "***" . substr($source_account, 3);
                            $narrationSinkAccount = "***" . substr($sink_account, 3);


                            $narration = empty($request_narration) || $request_narration != "undefined" ? $request_narration : "TRF from " . $sourceName. " to ". $sinkName;

                            if(Transfer::where("transaction_id", $tx_ref)->exists())
                                return $utils->message("error", "Network Error. Please Try Again.", 400);

                            if ($source_account === $sink_account)
                                return $utils->message("error", "Source and Destination Account are the same", 400);

                            $sink_account_name = $user->Name;
                            Log::info("########## Saving data before sending for transfer #########");
                            $transaction = new Transfer();
                            $transaction->currency_code = 1;
                            $transaction->intrabank = 1;
                            $transaction->minor_amount = $amount;
                            $transaction->minor_fee_amount = 0.0;
                            $transaction->minor_vat_amount = 0.0;
                            $transaction->name_enquiry_reference = "No Reference";
                            $transaction->narration = $narration;
                            $transaction->Response_code = 0;;
                            $transaction->sink_account_name = $sink_account_name;
                            $transaction->source_account = Account::where("user_id",  $user_id)->value("account_number");
                            $transaction->sink_account_number = $sink_account;
                            $transaction->source_account_provider_name = $sourceName;
                            $transaction->sink_account_provider_code = $bankCode ?? 892;
                            $transaction->source_account_provider_code = "090453";
                            $transaction->status = "Pending";
                            $transaction->transaction_id = $tx_ref;
                            $transaction->transaction_status = "No Status";
                            $transaction->transaction_type = "Single";
                            $transaction->transfer_type = $transfer_type;
                            $transaction->user_id = auth('sanctum')->user()->id;
                            $transaction->account_id =Account::where("user_id",  auth('sanctum')->user()->id)->value("id");
                            $transaction->save();

                            if (!$transaction){
                                Log::error("########## Data Not Saved. #########");
                                return $utils->message("error", "Data Not Saved", 400);

                            }
                            $transaction = json_decode(json_encode($transaction), true);
                            Log::info("########## Data Saved Successfully. #########", $transaction);

                            if ($transfer_type == "intra"){
                                $url = 'https://staging.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/LocalFundsTransfer';
                                $formParameters = [
                                    "Amount" => $amount,
                                    "FromAccountNumber" => $source_account,
                                    "ToAccountNumber" => $sink_account,
                                    "RetrievalReference" => $tx_ref,
                                    "Narration" => $narration,
                                    "AuthenticationKey" => env("BANK_ONE_AUTH_TOKEN")
                                ];
                            }else if ($transfer_type == "inter"){
                                $url = 'https://staging.mybankone.com/thirdpartyapiservice/apiservice/CoreTransactions/LocalFundsTransfer';
                                $formParameters = [
                                    "Amount" => $amount,
                                    "AppzoneAccount" => $source_account,
                                    "TransactionReference" => $tx_ref,
                                    "Payer" => $sink_account_name,
                                    "ReceiverBankCode" => $bankCode,
                                    "ReceiverAccountNumber" => $sink_account,
                                    "Narration" => $narration,
                                    "AuthenticationKey" => env("BANK_ONE_AUTH_TOKEN")
                                ];
                            }

                            Log::info("########## Sending for transfer #########");
                            $response = $client->request('POST', $url, [
                                'form_params' =>  $formParameters,
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
