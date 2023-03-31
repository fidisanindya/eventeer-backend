@extends('template_email/layout')
@section('content')
  {{-- Content --}}
  <img src="{{ $message->embed(public_path().'/assets/images/template_email/reset_password_icon.png') }}" alt="Reset Password Icon" style="margin-top: 33px;">
  <p style="font-size: 24px; font-weight: 500; font-family: Poppins, sans-serif;">Hi, <span style="font-weight: 600;">{{ $details['full_name'] }}</span></p>
  <p style="line-height: 28px;">You recently requested to reset your password for your Eventeer account.</p>
  <p style="line-height: 28px;">To reset your password, please visit link below</p>
  <a href="{{ $details['link_to'] }}" style="background-color: #0057EE; padding: 8px 12px; border-radius: 8px; width: 153px; height: 33px; font-size: 14px; font-weight: 500; color: #FFFFFF; line-height: 17px; text-decoration: none;">Reset Password</a>
  <p style="line-height: 28px;">This link expires in 5 minutes. If you did not initiate this action, we highly advise that you change your password as soon as possible and also notify us by replying to this mail. <br> Thank you! </p>
@endsection