<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class RaffleDrawsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->categories->name,
            'winningTags' => $this->winningTags->name,
            'start_date' => Carbon::parse($this->start_date)->format("d M, Y"),
            'end_date' => Carbon::parse($this->end_date)->format("d M, Y"),
            'current_winners' => $this->count_winners,
            'status' => $this->is_close,
            'win_nos' => $this->win_nos
        ];
    }
}
