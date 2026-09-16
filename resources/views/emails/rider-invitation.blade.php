<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <title>OrderMe Rider Invitation</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">

    <h2>Welcome to OrderMe</h2>

    <p>Hello {{ $user->name }},</p>

    <p>
        You have been invited to join OrderMe as a delivery rider.
    </p>

    <p>
        To activate your rider account, click the button below and
        create your password.
    </p>

    <p>
        <a
            href="{{ $activationUrl }}"
            style="
                display: inline-block;
                padding: 12px 20px;
                background-color: #d4a017;
                color: #ffffff;
                text-decoration: none;
                border-radius: 5px;
            "
        >
            Activate Rider Account
        </a>
    </p>

    <p>
        This invitation will expire in 24 hours.
    </p>

    <p>
        If you did not expect this invitation, you can safely ignore
        this email.
    </p>

    <p>
        Regards,<br>
        OrderMe Team
    </p>

</body>
</html>