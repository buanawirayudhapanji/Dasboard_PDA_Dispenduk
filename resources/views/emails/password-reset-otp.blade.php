<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kode OTP Ganti Password</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f1ef; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<span style="display:none; font-size:1px; color:#f4f1ef; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    Kode OTP Anda: {{ $code }}. Berlaku {{ $minutes }} menit dan hanya bisa dipakai sekali.
</span>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f1ef; padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="width:480px; max-width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(15,23,42,0.08);">

                {{-- Header --}}
                <tr>
                    <td style="background-color:#881337; padding:28px 32px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="72" valign="middle" style="width:72px; padding-right:16px;">
                                    <img
                                        src="{{ asset('images/lambang-kabupaten-jember.png') }}"
                                        width="56"
                                        alt="Lambang Kabupaten Jember"
                                        style="display:block; width:56px; height:auto; border:0;"
                                    >
                                </td>
                                <td valign="middle">
                                    <p style="margin:0; font-size:13px; letter-spacing:.04em; color:#fecdd3;">DISPENDUKCAPIL KABUPATEN JEMBER</p>
                                    <p style="margin:6px 0 0; font-size:19px; font-weight:600; color:#fff1f2;">Permintaan ganti password</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 16px; font-size:15px; line-height:24px; color:#1e293b;">Halo {{ $name }},</p>
                        <p style="margin:0 0 24px; font-size:15px; line-height:24px; color:#475569;">
                            Gunakan kode berikut untuk mengganti password akun Anda. Masukkan kode ini pada halaman yang sedang Anda buka.
                        </p>

                        {{-- OTP code --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="background-color:#fff1f2; border:1px solid #fecdd3; border-radius:12px; padding:20px 16px;">
                                    <p style="margin:0 0 6px; font-size:12px; letter-spacing:.08em; color:#9f1239; text-transform:uppercase;">Kode OTP Anda</p>
                                    <p style="margin:0; font-size:34px; font-weight:700; letter-spacing:.3em; color:#881337; font-family:'SFMono-Regular',Consolas,Menlo,monospace;">
                                        {{ $code }}
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:24px 0 0; font-size:14px; line-height:22px; color:#64748b;">
                            Kode berlaku selama {{ $minutes }} menit dan hanya dapat digunakan satu kali. Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari Dispendukcapil.
                        </p>

                        <p style="margin:20px 0 0; font-size:14px; line-height:22px; color:#64748b;">
                            Jika Anda tidak merasa meminta penggantian password, abaikan email ini. Password Anda tidak akan berubah.
                        </p>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 32px 28px; border-top:1px solid #f1f5f9;">
                        <p style="margin:0; font-size:13px; line-height:20px; color:#94a3b8;">
                            Salam hormat,<br>
                            Dispendukcapil Jember
                        </p>
                    </td>
                </tr>

            </table>

            <p style="margin:20px 0 0; font-size:12px; color:#94a3b8;">Email ini dikirim otomatis, mohon tidak membalas pesan ini.</p>
        </td>
    </tr>
</table>
</body>
</html>
