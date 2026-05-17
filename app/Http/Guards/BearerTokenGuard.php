<?php

namespace App\Http\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use App\Models\User;

class BearerTokenGuard implements Guard
{
    use GuardHelpers;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The name of the query string item from the request containing the API token.
     *
     * @var string
     */
    protected $inputKey = 'api_token';

    /**
     * Create a new token guard instance.
     *
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        // Return cached user if available
        if (!is_null($this->user)) {
            return $this->user;
        }

        // Get token from Authorization header (Bearer token)
        $token = $this->getTokenFromRequest();

        if (!empty($token)) {
            // Use direct query to find user by api_token
            $this->user = User::where('api_token', $token)->first();
        }

        return $this->user;
    }

    /**
     * Get the token for the current request.
     * Supports both Bearer token and query parameter formats.
     *
     * @return string|null
     */
    protected function getTokenFromRequest()
    {
        // Try to get Bearer token from Authorization header
        $header = $this->request->header('Authorization', '');
        
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }

        // Fall back to query parameter
        return $this->request->query($this->inputKey);
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        if (empty($credentials[$this->inputKey])) {
            return false;
        }

        return !is_null(User::where('api_token', $credentials[$this->inputKey])->first());
    }
}
