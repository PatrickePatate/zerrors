<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');
        $role = $organization?->roleFor($request->user());

        abort_if($role === null, 403, 'You are not a member of this organization.');

        if ($organization->require_2fa && ! $request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('security.edit')
                ->withErrors(['two_factor' => "\"{$organization->name}\" requires two-factor authentication — set it up to continue."]);
        }

        $request->attributes->set('organization_role', $role);

        return $next($request);
    }
}
