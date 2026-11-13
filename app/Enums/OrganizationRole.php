<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Owners and admins: settings, members, integrations, destructive actions.
     */
    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Anyone who may change organization data: everyone except viewers.
     */
    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Role values an owner may assign, keyed by value.
     *
     * @return array<string, string>
     */
    public static function assignable(bool $includeOwner): array
    {
        return collect(self::cases())
            ->reject(fn (self $role) => ! $includeOwner && $role === self::Owner)
            ->mapWithKeys(fn (self $role) => [$role->value => $role->value])
            ->all();
    }

    /**
     * Role values accepted when inviting someone (never owner).
     *
     * @return list<string>
     */
    public static function invitable(): array
    {
        return [self::Admin->value, self::Member->value, self::Viewer->value];
    }
}
