<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "sink_account_number" => $this->sink_account_number,
            "source_account" => $this->source_account,
            "dest_acct" => $this->sink_account_number,
            "status" => $this->status,
            "source_acct" => $this->source_account,
            "dest_acct_name" => $this->source_account_provider_name,
            "source_acct_name" => $this->sink_account_name,
            "amount" =>  number_format($this->minor_amount/100, 2),
            "transfer_date" => Carbon::parse($this->created_at)->format("d M, Y")
        ];
    }
}
