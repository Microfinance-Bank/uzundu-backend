<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Utils\Utils;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getDashboardData(Request $request, Utils $utils)
    {
        $transferToUzondu = Transfer::where("transfer_type", "intra")->sum("minor_amount");
        $transferToOtherBanks = Transfer::where("transfer_type", "inter")->sum("minor_amount");

        $data = [
            "transferToUzondu" => number_format($transferToUzondu/100, 2),
            "transferToOtherBanks" => number_format($transferToOtherBanks/100, 2)
        ];

        return $utils->message("success", $data, 200);


    }
}
