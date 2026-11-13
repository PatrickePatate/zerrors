<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    /**
     * Route names that stay available to read-only viewers on non-read
     * methods because they only affect the viewer's own membership.
     *
     * @var list<string>
     */
    public const VIEWER_ALLOWED_ROUTES = ['organizations.leave'];

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');
        $role = $organization?->roleFor($request->user());

        abort_if($role === null, 403, 'You are not a member of this organization.');

        if ($organization->require_2fa && ! $request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('security.edit')
                ->withErrors(['two_factor' => "\"{$organization->name}\" requires two-factor authentication — set it up to continue."]);
        }

        if ($role === OrganizationRole::Viewer->value
            && ! $request->isMethodSafe()
            && ! in_array($request->route()?->getName(), self::VIEWER_ALLOWED_ROUTES, true)) {
            abort(403, 'Viewers have read-only access to this organization.');
        }

        $request->attributes->set('organization_role', $role);

        return $next($request);
    }
}
