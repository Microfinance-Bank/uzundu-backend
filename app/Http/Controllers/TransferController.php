<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transfer;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function singleTransfer(Request $request, Utils $utils)
    {
        $account_number = $request->get("account_number");
        try {
            if(!Account::where("account_number", $account_number)->exists())
                return $utils->message("error", "Account Not Found", 404);
            try {
                // execute the request
                $data = "accountNumber=" .$account_number . "&authToken=" . env("BANK_ONE_AUTH_TOKEN") ;
//                $client = new \GuzzleHttp\Client();
//                $response = $client->request('GET', 'https://staging.mybankone.com/BankOneWebAPI/api/Account/GetAccountByAccountNumber/2?computewithdrawableBalance=false?' . $data);
//                $user = json_decode($response->getBody()->getContents());
//                if (isset($user->Message) && !empty($user))
//                    return $utils->message("success", $user->Message, 404);
                $balance = 500;

                if ($request->get("amount") > $balance)
                    return $utils->message("error", "Insufficient Funds", 400);

                $tx_ref = "Uzu_" . Str::random(10);
                if(Transfer::where("transaction_id", $tx_ref)->exists())
                    return $utils->message("error", "Transaction Ref Already Exists", 400);

                return $utils->message("success", "Successful", 200);

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return $utils->message("error", $e->getMessage() , 400);
//            $response = $e->getResponse();
//            return $utils->message("error", $response->getBody()->getContents() , 404);

            }
        } catch (\Throwable $e) {
            return $utils->message("error",$e->getMessage() , 404);
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
