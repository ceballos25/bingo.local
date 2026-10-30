<?php

/**
 * Usuario autenticado de la petición actual (rellenado por AuthGuard).
 */
final class AuthContext
{
    private static ?array $usuario = null;

    public static function establecer(array $usuario): void
    {
        self::$usuario = $usuario;
    }

    public static function limpiar(): void
    {
        self::$usuario = null;
    }

    public static function hayUsuario(): bool
    {
        return self::$usuario !== null;
    }

    public static function usuario(): array
    {
        if (self::$usuario === null) {
            throw new DomainException('No hay sesión autenticada.');
        }

        return self::$usuario;
    }

    public static function id(): int
    {
        return (int) self::usuario()['id'];
    }

    public static function organizacionId(): int
    {
        return (int) self::usuario()['organizacionId'];
    }

    public static function rol(): string
    {
        return (string) self::usuario()['rol'];
    }

    public static function esAdmin(): bool
    {
        return self::rol() === 'administrador';
    }

    public static function esOperadorOAdmin(): bool
    {
        $rol = self::rol();

        return $rol === 'administrador' || $rol === 'operador';
    }
}
