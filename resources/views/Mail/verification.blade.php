@extends('template_email/layout')
@section('content')
    <p style="font-size: 24px; font-weight: 500; font-family: Poppins, sans-serif;">Hi</p>
    <p style="line-height: 28px;">Just a friendly reminder to verify your email address.</p>
    <p style="line-height: 28px;">For security reasons, please help us by verifying your email address. Verify within 28 days of first signing up to avoid the deactivation of your account.</p>
    <form action="<?= route('VerificationEmail') ?>" method="POST">
        @csrf
        <input type="hidden" name="email" value="{{ $details['email'] }}">
        <input type="hidden" name="activation_code" value="{{ $details['activation_code'] }}">
        <button type="submit" style="background-color: #0057EE; padding: 8px 12px; border-radius: 8px; height: 33px; font-size: 14px; font-weight: 500; color: #FFFFFF; line-height: 17px; text-decoration: none; margin-bottom: 20px; border: none;">Verify email address</button>
    </form>
@endsection