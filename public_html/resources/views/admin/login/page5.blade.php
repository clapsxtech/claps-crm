<!DOCTYPE html>
<html>

<head>

    <title>TALLIA</title>
    <!DOCTYPE html>
    <html lang="en">
        <head>
            <title>TALLIA</title>
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="description" content="#">
            <meta name="keywords" content="#">
            <meta name="author" content="#">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
            <link rel="stylesheet" href="{{ asset('css/AdminLTE.min.css') }}">
            <link rel="stylesheet" href="{{ asset('css/font-awesome.min.css') }}">
            <link rel="stylesheet" href="{{ asset('css/style.css') }}">
            <link rel="stylesheet" href="{{ asset('css/main.css')}}">
            <link rel="stylesheet" href="{{ asset('css/util.css')}}">
            <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
        </head>
    <body>
        <div class="limiter">
            <div class="container-login100" style="background-image: url('/loginfiles/bg line.svg');">
            <div class="col-md-6">
                    <img class="deskbg" src="/loginfiles/bar chart illustration.svg" style="width:75%;height:75%;">
                    </div>
                    <div class="col-md-6">
                <div class="wrap-login100">
                    <form action="{{ route('login') }}" method="post" class="login100-form validate-form">
                    <center>
                            <img   src="/loginfiles/tallia logo.svg" alt="logo" style="margin-bottom:20px;padding-bottom:20px;height:70%;width:70%;" >
                            </center>
                        <div class="wrap-input100 validate-input" data-validate = "Enter email">
                        <i class="fa fa-user icon" style="padding:15px;" ></i>
                            <input class="input100" type="email" name="email" style="border-radius:10px;"  placeholder="email" color="">
                        </div>
                        <div class="wrap-input100 validate-input" data-validate="Enter password">
                        <i class="fa fa-key icon " style="padding:15px;"></i>
                            <input class="input100" type="password" style="border-radius:10px;" name="password" placeholder="Password">
                        </div>
                        <div class="container-login100-form-btn">
                            <button class="login100-form-btn">
                                Login
                            </button>
                        </div>
                        <div class="text-center p-t-30">
                            <a class="txt1" style=" color: #1a2654;" href="#">
                                Forgot Password?
                            </a>
                        </div>
                    </form>
                </div>
                <img class="deskbg2" src="tallia/images/bar chart illustration.svg" style="width:75%;height:75%;">
                </div>
            </div>
        </div>
        <div id="dropDownSelect1"></div>

        <script src="{{ asset('jquery.min.js') }}"></script>
        <script src="{{ asset('js/bootstrap.min.js') }}"></script>
        <script src="{{ asset('js/app.js') }}"></script>
        <script src="{{ asset('assets/plugins/iCheck/icheck.min.js') }}"></script>

    </body>
    </html>
