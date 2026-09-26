<!DOCTYPE html>
<html>

<head>

    <title>DRUG CARD</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="#">
    <meta name="keywords" content="#">
    <meta name="author" content="#">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.7 -->


    <link rel="stylesheet" href="{{asset('css/bootstrap.min.css')}}">
    <link rel="stylesheet" href="{{asset('css/AdminLTE.min.css')}}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{asset('css/font-awesome.min.css')}}">


    <link rel="stylesheet" href="{{ asset('css/style.css') }}">


    <link rel="stylesheet" href="style.css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">

</head>
<style>
    body {
        background-color: #ffffff;
        background-image: url('/loginfiles/primebg.svg');
        background-attachment: fixed;
        background-size: cover;
    }

    .cyer-footer {
        position: fixed;
        height: 35px;
        width: 100%;
        text-decoration: none;
        /* background-color: #FFFFFF; */
        left: 0;
        right: 0;
        bottom: 0px;
        display: block;
    }

    .login100-form-btn {
        font-family: Poppins-Medium;
        font-size: 16px;
        color: #FFFFFF;
        line-height: 1.2;
        width: 90%;
        display: -webkit-box;
        display: -webkit-flex;
        display: -moz-box;
        display: -ms-flexbox;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 0 5px;
        min-width: 120px;
        height: 35px;
        font-weight: bold;
        border-radius: 10px;
        background: #4f4f4f;
        position: relative;
        -webkit-transition: all 0.4s;
        -o-transition: all 0.4s;
        -moz-transition: all 0.4s;
        transition: all 0.4s;
    }
</style>

<body class="login-page fade-in" style="background-image: url('/loginfiles/drug_card.svg');background-size: cover;">
    <div class="zentrona-topnav">
        <div class="logo">
            <a href="#"><img src="/loginfiles/drugcard_white.png" style="height: 60px;" alt="billtrack logo"></a>
        </div>
        <div class="social">
            <a style="color:#62c2a7;text-shadow: 2px 2px #a7a7a8;" href="#"><i class="fa fa-facebook w3-large" aria-hidden="true"></i></a>
            <a style="color:#62c2a7;text-shadow: 2px 2px #a7a7a8;" href="#"><i class="fa fa-instagram" aria-hidden="true"></i></a>
            <a style="color:#62c2a7;text-shadow: 2px 2px #a7a7a8;" href="#"><i class="fa fa-whatsapp" aria-hidden="true"></i></a>
        </div>
    </div>

    <!--
<section class="content"  style="background-image: url(images/1.jpeg); background-repeat: no-repeat; height: 630px; width: cover;">
<center><h5 style="color:pink;  text-shadow: 3px 2px 8px #0000ff; font-size: 4.0em; -webkit-text-stroke-width: 3px; -webkit-text-stroke-color: black;"><b>ZenCare Office Management System</b></h5></center>-->


    <div class="login-box" style="margin-right:19%;margin-top:3%;">
        <div class="login-logo">
            <!--<a style="color:#00bf9a;text-shadow: 2px 2px #004f7f;" ><b>ZenCare - Login</b></a>-->
        </div>
        <!-- /.login-logo -->
        <div style=" color:#990033;border-radius:9px;" class="login-box-body">
            <center><img src="/loginfiles/Drugcard_login_icon.png" style="height: 60px;" alt="Prime Logo Light"></center>
            <p class="login-box-msg" id="session_message" style="color:#62c2a7;">Sign in to start your session</p>
            <form action="{{ route('login') }}" method="post">
                @csrf
                <div class="login_input">
                    <div class="login_page ">
                        <div class="form-group has-feedback">
                            <span class="glyphicon fa fa-user form-control-feedback"></span>
                            <input type="email" class="form-control" name="email" id="email" placeholder="Username" autocomplete="off">
                        </div>
                        <div class="form-group has-feedback">
                            <input type="password" class="form-control" name="password" id="password" placeholder="Password" autocomplete="off">
                            <i class="fa fa-key form-control-feedback"></i>
                        </div>
                        <div style="cursor:pointer;">
                            <a style="text-align: right;float: right;" id="foggot_id"><span style="color:#2e3192;">Forgot password?</span></a>
                            <br>
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-xs-12">
                            </div>
                        </div>
                        <div class="row" style="margin-left: 18px;">
                            <button style="background-color:#62c2a7;" type="submit" class="login100-form-btn">Login</button>
                        </div>
                    </div>
                </div>
            </form>
            <div class="forgot_password  ">
                <div class="form-group">
                    <input id="type" type="radio" name="type" checked value="0" onclick="getvalue(this.value);"><span style="color:#62c2a7;">Mobile</span>
                    <input id="type1" type="radio" name="type" value="1" onclick="getvalue(this.value);"><span style="color:#62c2a7;">Email</span>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control" id="pass_value" name="value" placeholder="Enter Details" autocomplete="off">
                    <span class="messages" id="messages"></span>
                </div>
                <div class="row" style="margin-left: 18px;">
                    <button style="background-color:#62c2a7;" id="sub_id" class=" btn login100-form-btn" onclick="getOtp(this.value)">RESET</button>
                </div>
            </div>
            <div class="enter_otp ">
                <div class="form-group">
                    <label style="color:#62c2a7;">ENTER OTP</label>
                    <input type="text" class="form-control" name="check_otp" id="check_otp" placeholder="Enter YOUR OTP" autocomplete="off">
                    <div><span id="timer"></span></div>
                </div>
                <div class="row" style="margin-left: 18px;">
                    <button style="background-color:#62c2a7;" id="otp_submit" onclick="submit_otp()" class=" btn login100-form-btn">SUBMIT</button>
                </div>
                <div style="cursor:pointer;" class="resend_otp">
                    <a style="text-align: right;float: right;color:#62c2a7;" id="resend_otp" onclick="resend_otp()">resend otp</a>
                </div>
            </div>
            <div class="reset_password ">
                <div class="form-group">
                    <label style="color:#62c2a7;">PASSWORD</label>
                    <input type="text" class="form-control" id="new_password" name="new_password" placeholder="NEW PASSWORD" autocomplete="off">
                </div>
                <div class="form-group">
                    <label style="color:#62c2a7;">CONFIRM PASSWORD</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="CONFIRM PASSWORD" autocomplete="off">
                </div>
                <div id="msg"></div>
                <div class="row" style="margin-left: 18px;">
                    <button style="background-color:#62c2a7;" id="reset" onclick="reset_password()" class=" btn login100-form-btn">SUBMIT</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    <footer class="cyer-footer">
        <div class="right">
            <b style="color:#62c2a7;"><small>Version 1.0</small></b>
        </div>
        <div class="left">
            <span style="color:#ffffff;"><small>Powered by</small> <a style="color:#ffffff;" target="_blank" href="https://frcybersolutions.com"><small>FR cyber solutions LLP.</small></a><small> &copy; 2018-<?php echo date('Y') ?>. All Rights Reserved.</small></span>
        </div>
    </footer>
    </div>
</body>

</html>
<script src="jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
<!-- iCheck -->
<script src="assets/plugins/iCheck/icheck.min.js"></script>
<script>
    $(document).ready(function() {
        $('.login_page').show();
        $('#session_message').show();
        $('.forgot_password').hide();
        $('.enter_otp').hide();
        $('.reset_password').hide();
        $(document.body).on('click', '#foggot_id', function() {
            $('#pass_value').val("");
            $('.messages').text("");
            $('.forgot_password').show();
            $('.login_page').hide();
            $('#session_message').hide();
            $('.reset_password').hide();;
        });
    });

    function myFunction() {
        var x = document.getElementById("password");
        if (x.type === "password") {
            x.type = "text";
        } else {
            x.type = "password";
        }
    }

    function getOtp(val) {

        var otp = $('#pass_value').val();
        if (otp != "") {
            $('#sub_id').attr('disabled', true);
            $.ajax({
                url: 'Forgot_Password',
                type: 'post',
                data: {
                    otp_gen: otp,
                    type_dat: val
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success == true) {
                        $('.messages').html('OTP Sent Successfully');
                        $('.forgot_password').hide();
                        $('.reset_password').hide();
                        $('.enter_otp').show();
                        $('.resend_otp').hide();

                        //   timer(remaining);
                        timer(30);


                    } else {
                        // alert('Email/Phon Not exist');
                        $('.messages').html('Email/Mobile Not exist');
                        $('#sub_id').attr('disabled', false);
                    }
                }
            });
        } else {
            $('.messages').html('Please Enter Email/Mobile');
        }
    }

    function submit_otp() {
        var otp = $('#check_otp').val();
        var data_value = $('#pass_value').val();

        $.ajax({
            url: 'otp_check',
            type: 'post',
            data: {
                data_value: data_value,
                otp: otp
            }, // /converting the form data into array and sending it to server
            dataType: 'json',
            success: function(response) {
                // alert(response);
                if (response.success == true) {
                    $('.messages').html('OTP Verified');
                    timerOn = false;
                    // $('#messages').html('OTP Verified');


                    $('.forgot_password').hide();
                    $('.enter_otp').hide();
                    $('.reset_password').show();
                } else {
                    $('.messages').html('Wrong OTP ');
                    // alert(4);
                }


            }

        });
    }

    function reset_password() {
        var newpassword = $('#new_password').val();
        var confirmpassword = $('#confirm_password').val();
        var change_value = $('#pass_value').val();
        $.ajax({
            url: 'new_password',
            type: 'post',
            data: {
                change_value: change_value,
                password: newpassword,
                cpassword: confirmpassword
            }, // /converting the form data into array and sending it to server
            dataType: 'json',
            success: function(response) {
                // alert(response);
                if (response.success == true) {
                    $('#msg').html(response.messages);
                    $('.forgot_password').hide();
                    $('.enter_otp').hide();
                    $('.reset_password').hide();
                    $('.login_page').show();
                    $('#session_message').show();
                } else {
                    $('#msg').html(response.messages);
                }


            }

        });


    }





    function getvalue(val) {
        var type = $('#sub_id').val(val);
    }
    let timerOn = true;
</script>
<script>
    function timer(remaining) {
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;

        m = m < 10 ? '0' + m : m;
        s = s < 10 ? '0' + s : s;
        document.getElementById('timer').innerHTML = m + ':' + s;
        remaining -= 1;

        if (remaining >= 0 && timerOn) {
            setTimeout(function() {
                timer(remaining);
            }, 1000);
            return;
        }

        if (!timerOn) {
            // Do validate stuff here
            return;
        }
        var time_value = $('#timer').text();
        change_otp(time_value);
        // Do timeout stuff here
        alert('Timeout.Resend otp');


    }

    function change_otp(time_value) {


        if (time_value == '00:00') {
            var data_value = $('#pass_value').val();


            $.ajax({
                url: 'change_otp',
                type: 'post',
                data: {
                    data_value: data_value
                }, // /converting the form data into array and sending it to server
                dataType: 'json',
                success: function(response) {
                    // alert(response);
                    if (response.success == true) {
                        $('.resend_otp').show();


                    }



                }

            });

        }
    }

    function resend_otp() {
        var val = $('#sub_id').val();

        getOtp(val);
        $('.resend_otp').hide();

    }
</script>
