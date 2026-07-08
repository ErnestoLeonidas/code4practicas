<?php

namespace App\Services;

/**
 * Utilidades de contraseñas: generación segura y hashing.
 */
final class Password
{
    // Conjuntos sin caracteres ambiguos (se excluyen 0, O, 1, l, I).
    private const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const MINUSCULAS = 'abcdefghijkmnopqrstuvwxyz';
    private const DIGITOS    = '23456789';
    private const SIMBOLOS   = '!@#$%*?-_=+';

    /**
     * Genera una contraseña aleatoria segura con al menos una mayúscula, una
     * minúscula, un dígito y un símbolo. Usa random_int (CSPRNG) y una mezcla
     * segura (Fisher-Yates). Excluye caracteres ambiguos.
     */
    public static function generar(int $longitud = 14): string
    {
        // Se necesitan al menos 4 caracteres para cubrir las cuatro categorías.
        $longitud = max($longitud, 4);

        $conjuntos = [self::MAYUSCULAS, self::MINUSCULAS, self::DIGITOS, self::SIMBOLOS];
        $todos     = implode('', $conjuntos);

        // Garantiza al menos un carácter de cada categoría.
        $caracteres = [];
        foreach ($conjuntos as $conjunto) {
            $caracteres[] = self::caracterAleatorio($conjunto);
        }

        // Rellena el resto desde el conjunto completo.
        for ($i = count($caracteres); $i < $longitud; $i++) {
            $caracteres[] = self::caracterAleatorio($todos);
        }

        self::mezclar($caracteres);

        return implode('', $caracteres);
    }

    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Devuelve un carácter aleatorio (CSPRNG) de la cadena dada.
     */
    private static function caracterAleatorio(string $conjunto): string
    {
        $indice = random_int(0, strlen($conjunto) - 1);
        return $conjunto[$indice];
    }

    /**
     * Mezcla segura in-place (Fisher-Yates) usando random_int.
     *
     * @param array<int, string> $items
     */
    private static function mezclar(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
}
