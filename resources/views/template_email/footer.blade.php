{{-- Footer --}}
<div style="margin: 0 auto; text-align: center; font-size: 16px; font-family: Roboto, 'Helvetica Neue', sans-serif; line-height: 32px;">
  <div style="margin: 0 8vw">
    <hr>
    <p style="font-family: 'Poppins', sans-serif; font-size: 24px; font-weight: 600; ">We available for Mobile App!</p>
    <p style="font-weight: 400; color: #5B6676;">Get Eventeer by installing our mobile app.<br>You can log in by using your existing emails address and password </p>
    <div style="margin: 16px auto;">
      <div style="margin: auto;">
        <a href="https://apps.apple.com/id/app/eventeer-event-management/id1599930175"><img src="{{ $message->embed(public_path().'/assets/images/template_email/app_store.png') }}" alt="App Store"></a>
        <a href="https://play.google.com/store/apps/details?id=com.amoeba.eventeer&hl=id&gl=US&pli=1"><img src="{{ $message->embed(public_path().'/assets/images/template_email/play_store.png') }}" alt="Play Store"></a>
      </div>
    </div>
  </div>

  <footer style="min-width: 100%; background-color: #E6EFFE; margin: 0 auto; text-align: center; font-size: 14px; line-height: 16px; font-weight: 400; color: #192434; padding: 26px 0">
    <img src="{{ $message->embed(public_path().'/assets/images/template_email/Logo.png') }}" alt="Logo Eventeer">
    <div style="margin: 10px auto">
      <a href=""><img src="{{ $message->embed(public_path().'/assets/images/template_email/Twitter.png') }}" alt="Twitter" style="margin-right: 27px;" width="14px"></a>
      <a href=""><img src="{{ $message->embed(public_path().'/assets/images/template_email/Facebook.png') }}" alt="Facebook" style="margin-right: 27px;" width="14px"></a>
      <a href=""><img src="{{ $message->embed(public_path().'/assets/images/template_email/Instagram.png') }}" alt="Instagram" width="14px"></a>
      <p>
        Digital Amoeba Space <br>
        Jalan Gegerkalong Hilir, Gegerkalong, Sukarasa, Kec. Sukasari <br>
        Kota Bandung, Jawa Barat, Indonesia 40152 <br>
        © 2022 Eventeer
      </p>
    </div>
  </footer>
</div>