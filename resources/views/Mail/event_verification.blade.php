@extends('template_email/layout_event')
@section('content')
    <p style="line-height: 28px;">Dear {{$details['full_name']}}</p>
    <p style="line-height: 35px;">Thank you for registering for the upcoming event, {{$details['title']}}. To ensure a seamless experience and accurate event management, we kindly request you to verify your personal information. This step is essential to confirm your participation and provide you with event-related updates.</p>
    <p>Please review the following details and verify their accuracy:</p>
    <p style="line-height: 35px;">Full Name: {{$details['full_name']}}</p>
    <p>Email: {{$details['email']}}</p>
    <p>Phone Number: {{$details['phone']}}</p>
    <p style="line-height: 35px;">If any of the above information is incorrect or incomplete, please reply to this email with the correct details. Note that providing accurate information is crucial for a smooth registration process.</p>
    <p>We appreciate your attention to this matter. Your verification will help us streamline the event proceedings and ensure that you receive all relevant event communications. We look forward to your active participation in {{$details['title']}}.</p>
    <p style="line-height: 35px;">Should you have any questions or require further assistance, please do not hesitate to reach out to our event support team at {{$details['phone']}} or {{$details['email']}}.</p>
    <p style="line-height: 35px;">Thank you for your cooperation.</p>
    <div style="text-align: center;">
        <a href="{{ $details['link_to'] }}" style="background-color: #0057EE; padding: 8px 12px; border-radius: 8px; height: 33px; font-size: 14px; font-weight: 500; color: #FFFFFF; line-height: 17px; text-decoration: none; margin-bottom: 20px; border: none;  margin-left:200px;">Verify email address</a>
    </div>
@endsection