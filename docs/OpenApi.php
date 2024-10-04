<?php

namespace App\Http\Docs;

use Nycorp\LiteApi\Models\ResponseCode;
use OpenApi\Attributes as OA;

#[OA\Info(version: "0.1", description: "This is my API", title: "My First API")]
class OpenApi
{
    #[OA\Get(path: '/up', operationId: 'HealthCheck', description: 'Check if the service is up', summary: 'Health Check', tags: ["health"])]
    #[OA\Response(response: '200', description: 'The data')]
    public function up()
    {

    }

    /**
     * @OA\Schema(
     *     schema="ResponseCode",
     *     type="integer",
     *     description="Standard response codes defined by the system / Codes de réponse standard définis par le système.",
     *     enum={
     *         ResponseCode::TOKEN_EXPIRED,
     *         ResponseCode::TOKEN_BLACK_LISTED,
     *         ResponseCode::TOKEN_INVALID,
     *         ResponseCode::TOKEN_NOT_FOUND,
     *         ResponseCode::TOKEN_USER_NOT_FOUND,
     *         ResponseCode::REQUEST_SUCCESS,
     *         ResponseCode::REQUEST_FAILURE,
     *         ResponseCode::REQUEST_VALIDATION_ERROR,
     *         ResponseCode::REQUEST_EXPIRED,
     *         ResponseCode::REQUEST_TRYING_TO_INSERT_DUPLICATE,
     *         ResponseCode::REQUEST_NOT_AUTHORIZED,
     *         ResponseCode::REQUEST_EXCEPTION,
     *         ResponseCode::REQUEST_NOT_FOUND,
     *         ResponseCode::REQUEST_WRONG_JSON_FORMAT,
     *         ResponseCode::REQUEST_SERVICE_NOT_AVAILABLE,
     *         ResponseCode::REQUEST_EMERGENCY,
     *         ResponseCode::AUTH_ACCOUNT_NOT_VERIFY,
     *         ResponseCode::AUTH_WRONG_USERNAME,
     *         ResponseCode::AUTH_WRONG_PASSWORD,
     *         ResponseCode::AUTH_WRONG_CREDENTIALS,
     *         ResponseCode::AUTH_ACCOUNT_VERIFIED,
     *         ResponseCode::AUTH_NOT_EXISTS
     *     },
     *     example=1000
     * )
     */
    public function responseCodeValue()
    {
        return ResponseCode::TOKEN_EXPIRED;
    }

    /**
     * @OA\Schema(
     *     schema="ResponseCodeDescriptions",
     *     type="object",
     *     description="Explanation of the response codes used in the API. All code values are returned as absolute values. / Explication des codes de réponse utilisés dans l'API. Toutes les valeurs des codes sont renvoyées en valeurs absolues.",
     *          properties={
     *          @OA\Property(property="TOKEN_EXPIRED", type="integer", example=1, description="The token has expired / Le token a expiré."),
     *          @OA\Property(property="TOKEN_BLACK_LISTED", type="integer", example=2, description="The token is blacklisted / Le token est dans la liste noire."),
     *          @OA\Property(property="TOKEN_INVALID", type="integer", example=3, description="The token is invalid / Le token est invalide."),
     *          @OA\Property(property="TOKEN_NOT_FOUND", type="integer", example=4, description="The token was not found / Le token n'a pas été trouvé."),
     *          @OA\Property(property="TOKEN_USER_NOT_FOUND", type="integer", example=5, description="The user associated with the token was not found / L'utilisateur associé au token n'a pas été trouvé."),
     *
     *          @OA\Property(property="REQUEST_SUCCESS", type="integer", example=1000, description="The request was successful / La requête a réussi."),
     *          @OA\Property(property="REQUEST_FAILURE", type="integer", example=1001, description="The request failed / La requête a échoué."),
     *          @OA\Property(property="REQUEST_VALIDATION_ERROR", type="integer", example=1002, description="Request validation error / Erreur de validation de la requête."),
     *          @OA\Property(property="REQUEST_EXPIRED", type="integer", example=1003, description="The request has expired / La requête a expiré."),
     *          @OA\Property(property="REQUEST_TRYING_TO_INSERT_DUPLICATE", type="integer", example=1004, description="Trying to insert a duplicate entry / Tentative d'insertion d'un doublon."),
     *          @OA\Property(property="REQUEST_NOT_AUTHORIZED", type="integer", example=1005, description="The request is not authorized / La requête n'est pas autorisée."),
     *          @OA\Property(property="REQUEST_EXCEPTION", type="integer", example=1006, description="An exception occurred while processing the request / Une exception s'est produite lors du traitement de la requête."),
     *          @OA\Property(property="REQUEST_NOT_FOUND", type="integer", example=1007, description="The request was not found / La requête n'a pas été trouvée."),
     *          @OA\Property(property="REQUEST_WRONG_JSON_FORMAT", type="integer", example=1008, description="Incorrect JSON format in the request / Format JSON incorrect dans la requête."),
     *          @OA\Property(property="REQUEST_SERVICE_NOT_AVAILABLE", type="integer", example=1009, description="The service is not available / Le service n'est pas disponible."),
     *          @OA\Property(property="REQUEST_EMERGENCY", type="integer", example=1010, description="Emergency request / Requête d'urgence."),
     *
     *          @OA\Property(property="AUTH_ACCOUNT_NOT_VERIFY", type="integer", example=1100, description="The account is not verified / Le compte n'est pas vérifié."),
     *          @OA\Property(property="AUTH_WRONG_USERNAME", type="integer", example=1101, description="Incorrect username / Nom d'utilisateur incorrect."),
     *          @OA\Property(property="AUTH_WRONG_PASSWORD", type="integer", example=1102, description="Incorrect password / Mot de passe incorrect."),
     *          @OA\Property(property="AUTH_WRONG_CREDENTIALS", type="integer", example=1103, description="Incorrect credentials / Identifiants incorrects."),
     *          @OA\Property(property="AUTH_ACCOUNT_VERIFIED", type="integer", example=1104, description="The account is verified / Le compte est vérifié."),
     *          @OA\Property(property="AUTH_NOT_EXISTS", type="integer", example=1105, description="The account does not exist / Le compte n'existe pas.")
     *      }
     * )
     */
    public function responseCodeDescription()
    {

    }

    /**
     * @OA\Schema(
     *     schema="ApiResponse",
     *     type="object",
     *     @OA\Property(property="success", type="boolean", example=false,),
     *     @OA\Property(property="code", type="integer", example=1000,),
     *     @OA\Property(property="message", type="string", example="Request success"),
     *     @OA\Property(property="body", type="object", nullable=true, example={"key":"value"}),
     *     @OA\Property(property="token", type="string", nullable=true, description="present only on auth route"),
     *     @OA\Property(property="trace_id", type="string", nullable=true, description="present only when success=false")
     * )
     */
    public function apiResponse()
    {

    }
}
