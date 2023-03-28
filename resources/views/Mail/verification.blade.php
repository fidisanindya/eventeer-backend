This is for you

Verification Email Click Here
<form action="<?= route('VerificationEmail', $user->email) ?>" method="POST">
    @csrf
    <button type="submit">Verification</button>
</form>