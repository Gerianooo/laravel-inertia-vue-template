<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'app' => fn () => [
                'name' => config('app.name'),
            ],

            'flash' => fn () => session()->get('flash') ?: [],

            '$user' => fn () => User::with(['roles', 'permissions'])->find(auth()?->id()),
            '$permissions' => fn () => $request->user()?->permissions,
            '$roles' => fn () => $request->user()?->roles,
            // 'token' => function () use ($request) {
            //     if ($user = $request->user()) {
            //         $user->tokens()->delete();

            //         return $user->createToken(uniqid())->plainTextToken;
            //     }
            // },
            '$translations' => function () {
                $path = resource_path('lang/' . app()->getLocale() . '.json');

                return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
            },
        ]);
    }
}