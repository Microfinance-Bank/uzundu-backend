<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          "narration" => $this->Narration,
          "amount" =>  number_format($this->Amount/100, 2),
            "transactionDate" => Carbon::parse($this->TransactionDate)->format('d M, Y'),
        ];
    }
}
