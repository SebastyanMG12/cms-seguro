<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->verifyAndGetTimestep($secret, $code, null) !== false;
    }

    /**
     * Devuelve el intervalo de tiempo aceptado, o false si el código es inválido
     * o ya fue usado (intervalo menor o igual al último aceptado).
     */
    public function verifyAndGetTimestep(string $secret, string $code, ?int $lastTimestep): int|false
    {
        $result = $this->google2fa->verifyKeyNewer($secret, $code, $lastTimestep ?? 0, 1);

        if ($result === true) {
            $result = $this->google2fa->getTimestamp();
        }

        return $result === false ? false : (int) $result;
    }

    public function currentCode(string $secret): string
    {
        return $this->google2fa->getCurrentOtp($secret);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /**
     * @return array{plain: array<int, string>, hashed: array<int, string>}
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $code = Str::lower(Str::random(5).'-'.Str::random(5));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }
}
