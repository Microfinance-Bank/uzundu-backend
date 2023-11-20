<?php

namespace App\Execs;

use App\Models\Account;
use App\Models\Agent;
use App\Utils\Utils;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

class Execs
{


    public function createUser($auth_type, $userFromApi, $userRequest): int
    {
            $user = new User();
            $user->username  = $userRequest->get("username");
            $user->password  = Hash::make($userRequest->get("password"));
            $user->created_by  = $userRequest->get("username");
            $user->last_modified_by  = $userRequest->get("username");
            $user->authentication_type  = $auth_type ?? "No Auth Type";
            $user->phone = $userFromApi[0]->PhoneNumber;;
            $user->email = $userFromApi[0]->Email ?? null;
            $user->save();
            return (int)$user->id;
    }


    public function createAccount($user, $user_id, $userRequest): Account
    {
            $account = new Account();
            $account->account_name = $user[0]->Accounts[0]->CustomerName;
            $account->account_number  = $user[0]->Accounts[0]->AccountNumber;
            $account->account_type =  $user[0]->Accounts[0]->AccountType;
            $account->email = $user[0]->Email;
            $account->added_by = $userRequest->get("username");
            $account->phone = $userRequest->get("phone");
            $account->user_id = $user_id;
            $account->save();
            return $account;

    }
}
