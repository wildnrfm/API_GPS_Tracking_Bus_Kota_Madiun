<?php

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\DeviceSession;
use App\Models\User;

class DeviceSessionUserProvider extends EloquentUserProvider {
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($id)
    {
        return $this->createModel()->newQuery()->find($id);
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials)
    {
        // This is for login form - not used for bearer token
        return parent::retrieveByCredentials($credentials);
    }

    /**
     * Retrieve a user by their unique token.
     */
    public function retrieveByToken($id, $token)
    {
        // Check if token exists in DeviceSession
        $deviceSession = DeviceSession::where('api_token', $token)
            ->where('user_id', $id)
            ->where('expires_at', '>', now())
            ->first();

        if (!$deviceSession) {
            return null;
        }

        return User::find($id);
    }

    /**
     * Update the "remember token" for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Not needed for API token based auth
    }

    /**
     * Retrieve a user by the given credentials, using an alternative to the first.
     */
    public function retrieveByCredentialsByAlternativeAuthentication(array $credentials)
    {
        // Check if we have a bearer token
        $token = request()->bearerToken();
        if (!$token) {
            return null;
        }

        // Find device session with this token
        $deviceSession = DeviceSession::where('api_token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$deviceSession) {
            return null;
        }

        return User::find($deviceSession->user_id);
    }
}
