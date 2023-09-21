@extends('template_email/layout')
@section('content')
  {{-- Content --}}
  <img src="{{ $message->embed(public_path().'/assets/images/template_email/reset_password_icon.png') }}" alt="Reset Password Icon" style="margin-top: 33px;">
  <p style="font-size: 24px; font-weight: 500; font-family: Poppins, sans-serif;">Hi, <span style="font-weight: 600;">{{ $details['full_name'] }}</span></p>
  <p style="line-height: 28px;">You recently requested to reset your password for your Eventeer account.</p>
  <p style="line-height: 28px;">To reset your password, please insert this OTP code below in Eveenter Apps</p>
  <a style="font-size: 28px; font-weight: 700; color: #000000; line-height: 17px; letter-spacing: 3px; text-decoration: none;"> {{$details['otp']}}</a>
  <p style="line-height: 28px;">This OTP code expires in 3 minutes. If you did not initiate this action, we highly advise that you change your password as soon as possible and also notify us by replying to this mail. <br> Thank you! </p>
@endsection