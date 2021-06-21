<?php
echo 1;
echo 2;
echo 3;

namespace App\Http\Controllers;

use App\Model\userAccount;
use App\Model\relationship;
use App\Model\relation_name;
use App\Model\position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Lib\Formatter;
use Illuminate\Support\Facades\DB;
use Session;

class carlockController extends Controller
{
	//我哪隻阿
	//我哪隻阿
    public function register(Request $request)
    {
        $account=$request->input('account');
        $password=$request->input('password');
        $name=$request->input('name');
        $email=$request->input('email');
        $phone=$request->input('phone');
        $type=$request->input('type');
        $check=userAccount::where('account','=',$account)->first();
        if($check==null)
        {
            //註冊用戶
            $newAccount = new userAccount();
            $newAccount->timestamps=false;
            $newAccount->account=$account;
            $newAccount->passwd=Hash::make($password);
            $newAccount->name=$name;
            $newAccount->mail=$email;
            $newAccount->phone=$phone;
            $newAccount->type=$type;
            $newAccount->save();
            return Formatter::Infomake(true,'success');
        }
        else
        {
            return Formatter::Infomake(false,'fail');
        }
    }

    public function loginCheck(Request $request)
    {
        $account=$request->input('account');
        $password=$request->input('password');
        if($check=userAccount::where('account','=',$account)->first())
        {
            if(Hash::check($password,$check->passwd))
            {
                Session::put('userInfo',$check);
                Session::put('systemInfo','登入成功');

                $data=Formatter::Datamake($account,$check->name,$check->type);
                return Formatter::Infomake(true,'登入成功',$data->getData());
            }
            else
            {
                return Formatter::Infomake(false,'登入失敗');
            }
        }
        else
        {
            return Formatter::Infomake(false,'登入失敗');
        }
    }

    public function changepwd(Request $request)
    {
        $account=$request->input('account');
        $newpwd=$request->input('newpwd');
        if($check=userAccount::where('account','=',$account)->first())
        {
            $AC=Session::get('userInfo');
            $ACNewpwd=userAccount::find($AC->id);
            $ACNewpwd->timestamps=false;
            $ACNewpwd->passwd=Hash::make($newpwd);
            $ACNewpwd->save();
            return Formatter::Infomake(true,'成功');
        }
        else
        {
            return Formatter::Infomake(false,'查無帳號');
        }
    }

    public function forgetpwd(Request $request)
    {
        $account=$request->input('account');
        $email=$request->input('email');
        if($check=userAccount::where('account','=',$account)->where('mail','=',$email)->first())
        {
            $newpwd=$this->generateRandomString();
            DB::table('user')
                ->where('account','=',$account)
                ->update([
                    'passwd'=>Hash::make($newpwd),
                ]);
            return Formatter::Infomake(true,'更改成功',$newpwd);
        }
        else
        {
            return Formatter::Infomake(false,'查無帳號或帳號密碼有誤');
        }
    }

    public function logoutCheck()
    {
        Session::flush();
    }

    public function QRgenerator()
    {
        $user=Session::get('userInfo');
        $token=$this->generateRandomString(10);
        if($check=userAccount::where('account','=',$user->account)->first())
        {
            $userDB=userAccount::find($user->id);
            $userDB->timestamps=false;
            $userDB->token=$token;
            $userDB->save();
            return Formatter::Infomake(true,'success',$token);
        }
        else
        {
            return Formatter::Infomake(false,'fail');
        }
    }

    public function RSBuild(Request $request)
    {
        $user=Session::get('userInfo');
        $token=$request->input('token');
        if($check=userAccount::where('token','=',$token)->first()) {
            try {
                DB::transaction(function () use ($user, $token) {
                    $userDB = userAccount::where('token', '=', $token)->first();
                    $userDB->timestamps = false;
                    $srcid = $userDB->id;
                    $userDB->token = NULL;
                    $userDB->save();
                    $RSDB = new relationship();
                    $RSDB->timestamps = false;
                    $RSDB->src_user_id = $srcid;
                    $RSDB->dst_user_id = $user->id;
                    $RSDB->save();
                });
            }catch (\Exception $ex)
            {
                return Formatter::Infomake(false, 'fail transaction');
            }
            return Formatter::Infomake(true, 'success');
        }
        else
        {
            return Formatter::Infomake(false,'fail');
        }
    }

    public function search_RSname()
    {
        $user=Session::get('userInfo');
        if($check=relation_name::where('dst_user_id','=',$user->id)->get())
        {
            $data=[];
            foreach($check as $label)
            {
                $data[]=[
                    'id' => $label->id,
                    'name' => $label->name,
                ];
            }
            return Formatter::Infomake(true,'success',$data);
        }
        else
        {
            return Formatter::Infomake(false,'fail');
        }
    }

    public function updateLocation(Request $request)
    {
        $user=Session::get('userInfo');
        $lng=$request->input('lng');
        $lat=$request->input('lat');
        if($check=position::where('user_id','=',$user->id)->first())
        {
            $newlocation=position::where('user_id','=',$user->id)->first();
            $newlocation->timestamps=false;
            $newlocation->lng=$lng;
            $newlocation->lat=$lat;
            $newlocation->save();
            return Formatter::Infomake(true,'success');
        }
        else
        {
            if($check=userAccount::where('id','=',$user->id)->first())
            {
                $newlocation= new position();
                $newlocation->user_id=$user->id;
                $newlocation->timestamps=false;
                $newlocation->lng=$lng;
                $newlocation->lat=$lat;
                $newlocation->save();
                return Formatter::Infomake(true,'success');
            }
            else
            {
                return Formatter::Infomake(false,'no register');
            }
        }
    }

    public function requestLocation(Request $request)
    {
        $gid=$request->input('id');
        if($check=position::where('user_id','=',$gid)->first())
        {
            $lng=$check->lng;
            $lat=$check->lat;

            $data=Formatter::Positionmake($lng,$lat);
            return Formatter::Infomake(true,'success',$data->getData());
        }
        else
        {
            return Formatter::Infomake(false,'fail');
        }
    }

    //隨機亂數
    public function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    //檢查用
    public function show_register()
    {
        return view('register');
    }
    public function show_login()
    {
        return view('login');
    }
    public function  show_forgetpwd()
    {
        return view('forgetpwd');
    }
    public function  show_changepwd()
    {
        return view('changepwd');
    }
    public function  show_RSBuild()
    {
        return view('RSBuild');
    }
    public function  show_updateLocation()
    {
        return view('updateLocation');
    }
    public  function  show_requestLocation()
    {
        return view('requestLocation');
    }
}

