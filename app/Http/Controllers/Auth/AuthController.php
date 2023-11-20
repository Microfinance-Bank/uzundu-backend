<?php

namespace App\Http\Controllers\Auth;

use App\Execs\Execs;
use App\Http\Resources\Customer;
use App\Mail\PasswordCodeEmail;
use App\Mail\VerifyCodeMail;
use App\Models\Account;
use App\Models\NewCustomer;
use App\Models\User;
use App\Utils\CurlGet;
use App\Utils\CurlPost;
use App\Utils\Utils;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\UserRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AuthController extends Controller
{
    public function createPin(Request $request, $username, Utils $utils)
    {
       if(!User::where("username", $username)->exists())
           return $utils->message("success", "User Not Found", 404);

        try {
            return  DB::transaction(function () use ($request, $username, $utils) {
                $user = User::where("username", "=", $username)->firstOrFail();
                $user->pin = Hash::make($request->get("pin"));
                $user->save();
                return $utils->message("success", $user, 200);

            });
        } catch (\Throwable $e) {
            return $utils->message("error",$e->getMessage() , 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/customer/register-user",
     *      tags={"Mobile"},
     *     @OA\Parameter(
     *         name="username",
     *         in="query",
     *         description="username",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Password",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="first_name",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="last_name",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         description="phone",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="email",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Registration successful", @OA\JsonContent()),
     *     @OA\Response(response="401", description="Invalid credentials", @OA\JsonContent()),
     *     @OA\Response(response="422", description="validation Error", @OA\JsonContent())
     *
     * )
     */
    public function registerUser(UserRequest $userRequest, Utils $utils, Execs $execs)
    {
        $phone = $userRequest->get("phone");
        try {
            // execute the request
            $data = "phoneNumber=" . $userRequest->get("phone") . "&authToken=" . env("BANK_ONE_AUTH_TOKEN") ;
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://staging.mybankone.com/BankOneWebAPI/api/Customer/GetByCustomerPhoneNumber/2?' . $data);
            $user = json_decode($response->getBody()->getContents());
            if (isset($user->Message) && !empty($user))
                return $utils->message("success", $user->Message, 404);
            try {

              return  DB::transaction(function () use ($userRequest, $user, $execs, $utils) {

                    $user_id = $execs->createUser("", $user, $userRequest);
                    $user =  $execs->createAccount($user, $user_id, $userRequest);
                    $mailData = [
                        'title' => 'Verification Code',
                    ];
//                Mail::mailer("no-reply")->to($userRequest->get("email"))->send(new VerifyCodeMail($mailData));
                  $data = [
                      "username" => $userRequest->get("username")
                  ];
                    return $utils->message("success", $data, 200);
                });
            } catch (\Throwable $e) {
                return $utils->message("error",$e->getMessage() , 404);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $utils->message("error", "Network Error. Please Try Again" , 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/customer/verify-code",
     *      tags={"Mobile"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="email",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="code",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Verification successful"),
     *     @OA\Response(response="404", description="Code Not Found")
     * )
     */
    public function verifyCode(Request $request, Utils $utils)
    {

        $request->validate([
            "code" => "required|string",
            "email" => "required|string"
        ]);

        if(!User::where("email", $request->get("email"))->where("verify_code", $request->get("code"))->exists())
            return $utils->message("error", "Code Does Not Exist", 404);

        $user = User::where("email", $request->get("email"))->firstOrFail();
        $user->verified = 1;
        $user->update();
        return $utils->message("success","Verification Successful.", 404);

    }
    /**
     * @OA\Post(
     *     path="/api/v1/customer/forgot-password",
     *      tags={"Mobile"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="email",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Registration successful"),
     *     @OA\Response(response="401", description="Invalid credentials")
     * )
     */
    public function forgotPassword(Request $request, Utils $utils): JsonResponse
    {
        $request->validate([
            "email" => "required|string"
        ]);
        $email = $request->get("email");
        if (!User::where("email", $email)->exists())
            return $utils->message("error", "User Not Found", 404);
        $password_reset_code = random_int(100000, 999999);
        User::where("email", $email)->update(["password_reset" => $password_reset_code]);
        $mailData = [
            'title' => 'Reset your password',
            'code' => $password_reset_code
        ];
        Mail::mailer("no-reply")->to($email)->send(new PasswordCodeEmail($mailData));
        return $utils->message("success", $mailData, 200);

    }
    /**
     * @OA\Post(
     *     path="/api/v1/customer/update/password",
     *      tags={"Mobile"},
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="email",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="new_password",
     *         in="query",
     *         description="new_password",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="confirm_password",
     *         in="query",
     *         description="confirm_password",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Registration successful"),
     *     @OA\Response(response="401", description="Invalid credentials")
     * )
     */
    public function updatePassword(Request $request, Utils $utils)
    {
        $request->validate([
            "email" => "required|string",
            'new_password' => "required|string|required_with:confirm_password|same:confirm_password",
            'confirm_password' => "required|string"
        ]);

        if(!User::where('email',$request->get("email"))->exists())
            return $utils->message("error", "User Not Found", 404);


        User::where("email", $request->get("email"))->update(["password" => Hash::make($request->get("new_password"))]);
        return $utils->message("success", "Password Updated Successfully.", 200);

    }


    /**
     * @OA\Post(
     *     path="/api/v1/customer/verify-otp",
     *     summary="Authenticate user and generate Sactum token",
     *     tags={"General"},
     *     @OA\Parameter(
     *         name="code",
     *         in="query",
     *         description="code",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Verification successful", @OA\JsonContent()),
     *     @OA\Response(response="401", description="Invalid credentials", @OA\JsonContent()),
     *     @OA\Response(response="422", description="Validation Error", @OA\JsonContent())
     *
     * )
     */
    public function verifyOTP(Request $request, Utils $utils): JsonResponse
    {

        $request->validate([
            'code' => "required|integer",
        ]);
        if(User::where('email',$request->get("email"))->value("password_reset") !== $request->get("code"))
            return $utils->message("error", "Code is incorrect", 401);

        return $utils->message("success", "Code Verified Successfully.", 200);


    }
    /**
     * @OA\Post(
     *     path="/api/v1/login",
     *     summary="Authenticate user and generate Sactum token",
     *     tags={"General"},
     *     @OA\Parameter(
     *         name="username",
     *         in="query",
     *         description="Username",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="password",
     *         in="query",
     *         description="Password",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Login successful", @OA\JsonContent()),
     *     @OA\Response(response="401", description="Unauthorized", @OA\JsonContent()),
     *     @OA\Response(response="422", description="Validation Error", @OA\JsonContent())
     * )
     */
    public function login(LoginRequest $loginRequest, Utils $utils, Execs $execs)
    {
        if (!auth()->attempt(request()->only(['username', 'password']))) {
            return $utils->message( "error", "Invalid Username/Password", 401);
        }

        $user = Account::where("user_id", Auth::user()->id)->firstOrFail();
        $authUser = Auth::user();
        $success['token'] =  $authUser->createToken('MyAuthApp')->plainTextToken;
        $success['account_name'] =   $user->account_name;
        $success['account_number'] =  $user->last_name;
        $success['account_type'] =  $user->last_name;
        $success['username'] =  $authUser->username;
        $success['id'] =  $authUser->id;
        return $utils->message("success", $success, 200);
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'User successfully signed out']);
    }
}
