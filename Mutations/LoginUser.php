<?php

namespace App\GraphQL\Mutations;

use Joselfonseca\LighthouseGraphQLPassport\GraphQL\Mutations\BaseAuthResolver;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Illuminate\Http\Request;
use Joselfonseca\LighthouseGraphQLPassport\Exceptions\AuthenticationException;
use App\Exceptions\SendException;
use App\Events\SendMessage;


class LoginUser extends BaseAuthResolver
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        
        $user = $this->findUser($args['username']);
     if($user&&$user->email_verified_at&&!$user->is_delete){
         $credentials = $this->buildCredentials($args);
         $response = $this->makeRequest($credentials);

         if($user->first_time){
            $is_first_time = 0;
         } else {
            $is_first_time = 1;
         };

         $user->update(['first_time' => now()]);

       $this->validateUser($user);

       event( new SendMessage($user->id,['login user'=>'true', 'user'=>$user]) );
     
       
       return array_merge(
           $response,
           [
               'user' => $user,
           ],
           [
            'is_first_time' => $is_first_time
           ]
       );
     } else {
          if($user&&!$user->email_verified_at){
           throw new SendException(
             'error',
             __('messages.error_email_verification')
           );
          } else {
            throw new SendException(
                'error',
                __('messages.Incorrect_username_or_password')
            );          
      
          }

     }

    }

    protected function validateUser($user)
    {
        $authModelClass = $this->getAuthModelClass();
        if ($user instanceof $authModelClass && $user->exists) {
            return;
        }

        throw (new ModelNotFoundException())->setModel(
            get_class($this->makeAuthModelInstance())
        );
    }

    protected function getAuthModelClass(): string
    {
        return config('auth.providers.users.model');
    }

    protected function makeAuthModelInstance()
    {
        $modelClass = $this->getAuthModelClass();

        return new $modelClass();
    }

    public function makeRequest(array $credentials)
    {
        $request = Request::create('oauth/token', 'POST', $credentials, [], [], [
            'HTTP_Accept' => 'application/json',
        ]);
        $response = app()->handle($request);
        $decodedResponse = json_decode($response->getContent(), true);
        if ($response->getStatusCode() != 200) {
            throw new SendException(
                'error',
                __('messages.Incorrect_username_or_password')
            );
        
        }

        return $decodedResponse;
    }

    protected function findUser(string $username)
    {
        $model = $this->makeAuthModelInstance();

        if (method_exists($model, 'findForPassport')) {
            return $model->findForPassport($username);
        }

        return $model->where(config('lighthouse-graphql-passport.username'), $username)->first();
    }

  
}
