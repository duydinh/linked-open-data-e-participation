<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Controller;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\WebPageController;
use App\Images\ImageProcessing;
use Illuminate\Support\Facades\Config;

class AuthController extends Controller {
    /*
      |--------------------------------------------------------------------------
      | Registration & Login Controller
      |--------------------------------------------------------------------------
      |
      | This controller handles the registration of new users, as well as the
      | authentication of existing users. By default, this controller uses
      | a simple trait to add these behaviors. Why don't you explore it?
      |
     */

use AuthenticatesAndRegistersUsers,
    ThrottlesLogins;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct() {

        $this->middleware('guest', ['except' => 'getLogout']);
    }

    /**
     * Create a new user instance after a valid registration
     * @param  array  $data
     * @return User
     */
    protected function create(array $data) {
        $userId = UserController::idUsersMax() + 1;
        $user = array('id' => $userId, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'group' => $data['group'], 'email' => $data['email'],
            'password' => $data['password'], 'role' => '',
            'nationality' => $data['nationality']);

        if (isset($data['avatar'])) {
            $avatarFileName = ImageProcessing::processPhotoAndResize($data['avatar'], "images/avatars", 30, $userId);
            $avatar = $avatarFileName;
        } else {
            $avatar = '/images/avatars/avatar-default.png';
        }
        $userURI = UserController::buildUserURI($userId);
        $label = UserController::buildUserLabel($userId, $user);
        $role = UserController::buildUserRoleURI($user);
        $group = UserController::buildUserGroupURI($user);
        $nationality = UserController::buildUserNationalityURI($user);
        $avatarUri = UserController::buildUserAvatarURI($avatar);
        UserController::addUser($userURI, $userId, $user['first_name'], $user['last_name'], $label, $role, $group, $nationality, $avatarUri, $user['email'], $user['password']);

        return $user;
    }

    /**
     * Show the application login form
     */
    public function getLogin(Request $request) {
        WebPageController::updateLocale($request->get('lang'));
        return view('auth.login');
    }

    /**
     * Handle a login request to the application
     */
    public function postLogin(Request $request) {
        if ($request->get('email') == '' || $request->get('password') == '') {
            return redirect("auth/login/?lang=" . $request->get('lang'))
                            ->withInput($request->only($this->loginUsername(), 'remember'))
                            ->withErrors([
                                trans('lodepart.mail-missing'),
                                trans('lodepart.password-missing')
            ]);
        }
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        $throttles = $this->isUsingThrottlesLoginsTrait();

        if ($throttles && $this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }
        WebPageController::updateLocale($request->get('lang'));
        $user = UserController::connectUser($request->get('email'), $request->get('password'));
        if ($user != null) {
			if ($user->active->value === "true"){
				Session::put('user', $user);
				return redirect()->intended('/?lang=' . $request->get('lang'));
			}else{
				return redirect("auth/login/?lang=" . $request->get('lang'))
                            ->withInput($request->only($this->loginUsername(), 'remember'))
                            ->withErrors([
                                trans('lodepart.account-not-activated')
							]);	
			}            
        } else {
            return redirect("auth/login/?lang=" . $request->get('lang'))
                            ->withInput($request->only($this->loginUsername(), 'remember'))
                            ->withErrors([
                                trans('lodepart.id-invalidate')
							]);
        }
    }

    /**
     * Log the user out of the application
     */
    public function getLogout(Request $request) {
        Session::forget('user');
        return redirect('auth/login?lang=' . $request->get('lang'));
    }

    /**
     * Show the application registration form
     */
    public function getRegister(Request $request) {
        WebPageController::updateLocale($request->get('lang'));
        return view('auth.register');
    }

    /**
     * Register user
     */
    public function postRegister(Request $request) {
        if ($request->get('first_name') == '' || $request->get('last_name') == '' || $request->get('email') == '' || $request->get('password') == '' || $request->get('password_confirmation') == '') {
            return redirect("auth/register/?lang=" . $request->get('lang'))
                            ->withInput()
                            ->withErrors(trans('lodepart.mandatory-id'));
        }
        if ($request->get('password') != $request->get('password_confirmation') || strlen($request->get('password')) < 6) {
            return redirect("auth/register/?lang=" . $request->get('lang'))
                            ->withInput()
                            ->withErrors(trans('lodepart.different-password'));
        }
        $existUser = UserController::selectUser($request->get('email'));
        if ($existUser != null) {
            return redirect("auth/register/?lang=" . $request->get('lang'))
                            ->withInput()
                            ->withErrors(trans('lodepart.already-mail'));
        } else {
            $newUser = $this->create($request->all());
            // instead of redirecting the user to the intended page, we send an email for activating the user account
            //$user = UserController::connectUser($request->get('email'),$request->get('password'));
            //Session::put('user', $user);			
            //return redirect()->intended('/?lang=' . $request->get('lang'));
            // Generate token and date token

            $token = str_random(25);

            $now = date("c");
            $dateToken = date("c", strtotime("+15 minute", strtotime($now)));
            UserController::updateToken($newUser['id'], $token, $dateToken);


            if (env('SEND_MAIL') === true) {
                $lang = Config::get('app.locale');
                $headers = "From: Lodepart <OPDL-EPARTICIPATION@publications.europa.eu>";
                $headers = 'MIME-Version: 1.0' . "\r\n";
                $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                $message = "<html><body>" .
                        trans('keywords.dear') . ' ' . $newUser['first_name'] . ' ' . $newUser['last_name'] . ',<br /><br />' .
                        trans('lodepart.confirm-email-text') .
                        "<br /><br /> <a href='" . env('SITE_NAME') . "/activate-account?lang=" . $lang . "&token=" . $token . "'><button>" . trans('keywords.confirm-email') . "</button></a> <br /><br /><br />" .
                        trans('lodepart.confirm-email-thank-you') .
                        "<br /><br /><br /><img src='" . env('SITE_NAME') . "/images/logos/lod-logo.png' alt='e-participation / Linked Open Data' />" .
                        "</body></html>";

                mail($request->get('email'), trans('lodepart.confirm-email'), $message, $headers);
            }
            return view('auth.activate-account')->with('email', $request->get('email'));
        }
    }

    /*     * * Activate user account ** */

    public function activateAccount(Request $request) {
        $user = UserController::selectUserFromToken($request->get('token'));
        if ($user != null) {
            // activate user account here
            UserController::activateAccount($user->mail->value);
            Session::put('user', $user);
        }
        return redirect()->intended('/?lang=' . $request->get('lang'));
    }

}
