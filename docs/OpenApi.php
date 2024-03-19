<?php

namespace App\Http\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(version: "0.1", description: "This is my API", title: "My First API")]
class OpenApi
{
    #[OA\Get(path: '/')]
    #[OA\Response(response: '200', description: 'The data')]
    public function test()
    {

    }
}
