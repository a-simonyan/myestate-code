<?php

namespace App\GraphQL\Queries;

use App\Support;
use Auth;

class UserSupports
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args)
    {
        $user_auth_id   = Auth::user()->id;
        $supportClass = Support::with('user')->where('user_id',  $user_auth_id);
      
        if(isset($args['is_support_status'])) {
            $supportClass = $supportClass->whereIn('is_support_status', $args['is_support_status']);
        }

        if(!empty($args['order_by'])) {
            $supportClass = $supportClass->orderBy('id', $args['order_by']);
        } else {
            $supportClass = $supportClass->orderBy('id', 'DESC');
        }

        /*add paginate*/
        if(!empty($args['paginate'])){
            $first = !empty($args['paginate']['first']) ? $args['paginate']['first'] : 10;
            $page  = !empty($args['paginate']['page']) ? $args['paginate']['page'] : 1;

            return $supportClass->paginate($first,['*'],'page', $page);
        }

        return $supportClass->get();

    }
}
