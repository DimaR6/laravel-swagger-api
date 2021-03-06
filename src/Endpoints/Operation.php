<?php

namespace LaravelApi\Endpoints;


use Calcinai\Strut\Definitions\BodyParameter;
use Calcinai\Strut\Definitions\HeaderParameterSubSchema;
use Calcinai\Strut\Definitions\Operation as StrutOperation;
use Calcinai\Strut\Definitions\QueryParameterSubSchema;
use Calcinai\Strut\Definitions\Response;
use Calcinai\Strut\Definitions\Responses;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationRuleParser;
use LaravelApi\Definition;


/**
 * @method Operation defaults( string $key, mixed $value )
 * @method Operation name( string $name )
 * @method Operation uses( \Closure | string $action )
 * @method Operation setUri( string $uri )
 * @method Operation prefix( string $prefix )
 * @method Operation domain( string $domain = null )
 * @method Operation where( array | string $name, string $expression = null )
 * @method Operation middleware( array | string $middleware = null )
 * @method Operation fallback()
 * @method string uri()
 * @method string getName()
 * @method mixed getAction( string | null $key = null )
 * @method string getActionName()
 * @method string getActionMethod()
 * @method string getPrefix()
 * @method string|null getDomain()
 */
class Operation extends StrutOperation
{
	
	/**
	 * @var Route
	 */
	protected $route;
	
	/**
	 * @var ValidationRuleParser
	 */
	protected $ruleParser;
	
	
	public function __construct ( $data = [] )
	{
		parent::__construct ( $data );
		
		$this->setResponses ( Responses::create () );
	}
	
	
	/**
	 * @param Route $route
	 * @param array $parameters
	 * @return Operation
	 */
	public function setRoute ( Route $route, array $parameters = [] )
	{
		$this->route = $route;
		
		$this->initTags ( (array) $route->getAction ( 'tags' ) );
		
		if ( ! $route->getAction ( 'uses' ) instanceof \Closure )
		{
			$this->initOperationId ( $route );
		}
		
		if ( config ( 'api.parse_route_parameters' ) )
		{
			$this->parseRouteParameters ( $route->getDomain () . $route->uri (), $parameters );
		}
		
		return $this;
	}
	
	
	/**
	 * @param array $tags
	 * @return Operation
	 */
	protected function initTags ( array $tags )
	{
		foreach ( $tags as $tag )
		{
			$this->addTag ( $tag );
		}
		return $this;
	}
	
	
	/**
	 * @param Route $route
	 * @return Operation
	 */
	protected function initOperationId ( Route $route )
	{
		$operationId = Str::camel ( $route->getActionMethod () );
		
		return $this->setOperationId ( $operationId );
	}
	
	
	/**
	 * @param string $uri
	 * @param array  $parameters
	 * @return Operation
	 */
	public function parseRouteParameters ( $uri, array $parameters = [] )
	{
		preg_match_all ( '/\{(.*?)\}/', $uri, $matches );
		
		array_map ( function ( $match ) use ( $parameters ) {
			
			$required = ! Str::endsWith ( $match, '?' );
			$name = trim ( $match, '?' );
			
			if ( Arr::has ( $parameters, $match ) )
			{
				$parameter = clone $parameters[ $name ];
				if ( $required )
				{
					$parameter->setRequired ( true );
				} elseif ( $parameter->has ( 'required' ) )
				{
					$parameter->remove ( 'required' );
				}
				$this->addParameter ( $parameters[ $name ] );
			} else
			{
				$this->addPathParameter ( $name, null, $required, 'string' );
			}
			
		}, $matches[ 1 ] );
		
		return $this;
	}
	
	
	/**
	 * @param string $name
	 * @param array  $arguments
	 * @return Endpoint
	 */
	public function __call ( $name, $arguments )
	{
		if ( method_exists ( $this->route, $name ) )
		{
			$result = call_user_func_array ( [ $this->route, $name ], $arguments );
			return ( $result instanceof Route ) ? $this : $result;
		}
		
		throw new \BadMethodCallException( "Method {$method} does not exist." );
	}
	
	
	/**
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @param string          $type
	 * @return Operation
	 */
	public function addHeaderParameter ( $name, $descriptionOrCallback = null, $required = false, $type = 'string' )
	{
		return $this->registerParameter ( HeaderParameterSubSchema::class, $name, $descriptionOrCallback, $required, $type );
	}
	
	
	/**
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @param string          $type
	 * @return Operation
	 */
	public function addQueryParameter ( $name, $descriptionOrCallback = null, $required = false, $type = 'string' )
	{
		return $this->registerParameter ( QueryParameterSubSchema::class, $name, $descriptionOrCallback, $required, $type );
	}
	
	
	/**
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @param string          $type
	 * @return Operation
	 */
	public function addPathParameter ( $name, $descriptionOrCallback = null, $required = false, $type = 'string' )
	{
		return $this->registerParameter ( Parameters\PathParameter::class, $name, $descriptionOrCallback, $required, $type );
	}
	
	
	/**
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @param string          $type
	 * @return Operation
	 */
	public function addFormDataParameter ( $name, $descriptionOrCallback = null, $required = false, $type = 'string' )
	{
		if ( ! $this->has ( 'consumes' ) )
		{
			$this->setConsumes ( [ 'application/x-www-form-urlencoded' ] );
		}
		return $this->registerParameter ( Parameters\FormDataParameter::class, $name, $descriptionOrCallback, $required, $type );
	}
	
	
	/**
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @return Operation
	 */
	public function addBodyParameter ( $name, $descriptionOrCallback = null, $required = false )
	{
		return $this->registerParameter ( BodyParameter::class, $name, $descriptionOrCallback, $required );
	}
	
	
	/**
	 * @param string          $parameterType
	 * @param string          $name
	 * @param string|callable $descriptionOrCallback
	 * @param bool            $required
	 * @param string          $type
	 * @return Operation
	 */
	protected function registerParameter ( $parameterType, $name, $descriptionOrCallback = null, $required = false, $type = 'string' )
	{
		$parameter = $this->getOrCreateParameter ( $parameterType, $name );
		
		if ( method_exists ( $parameter, 'setType' ) )
		{
			$parameter->setType ( $type );
		}
		
		if ( $required )
		{
			$parameter->setRequired ( $required );
		} elseif ( $parameter->has ( 'required' ) )
		{
			$parameter->remove ( 'required' );
		}
		
		if ( $descriptionOrCallback instanceof \Closure )
		{
			$descriptionOrCallback( $parameter );
		} elseif ( is_string ( $descriptionOrCallback ) )
		{
			$parameter->setDescription ( $descriptionOrCallback );
		}
		
		return $this;
	}
	
	
	/**
	 * @param string $parameterType
	 * @param string $name
	 * @return QueryParameterSubSchema|Parameters\PathParameter|Parameters\FormDataParameter|HeaderParameterSubSchema|BodyParameter
	 */
	protected function getOrCreateParameter ( $parameterType, $name )
	{
		if ( $this->has ( 'parameters' ) )
		{
			if ( $existingParameter = $this->retrieveParameter ( $name, $parameterType ) )
			{
				return $existingParameter;
			}
		}
		
		$parameter = new $parameterType( compact ( 'name' ) );
		
		$this->addParameter ( $parameter );
		
		return $parameter;
	}
	
	
	/**
	 * @param string $name
	 * @param string $type
	 * @return QueryParameterSubSchema|Parameters\PathParameter|Parameters\FormDataParameter|HeaderParameterSubSchema|BodyParameter|null
	 */
	protected function retrieveParameter ( $name, $type )
	{
		$parameters = Collection::make ( $this->getParameters () );
		
		return $parameters->filter ( function ( $param ) use ( $name, $type ) {
			return ( $param instanceof $type and $param->getName () === $name );
		} )->first ();
	}
	
	
	/**
	 * @param integer $code
	 * @param string  $description
	 * @return Operation
	 * @throws \Exception
	 */
	public function addResponse ( $code, $description )
	{
		$response = Response::create ( compact ( 'description' ) );
		
		$this->getResponses ()->set ( $code, $response );
		
		return $this;
	}
	
	
	/**
	 * @param string $request
	 * @return Operation
	 */
	public function bindRequest ( $request )
	{
		$rules = ( new $request )->rules ();
		
		return $this->bindRules (
			$this->getValidationRuleParser ()
				 ->explode ( $rules )->rules
		);
	}
	
	
	/**
	 * @param array $requestRules
	 * @return Operation
	 */
	protected function bindRules ( array $requestRules )
	{
		foreach ( $requestRules as $name => $rules )
		{
			$this->addFormDataParameter ( $name, function ( $param ) use ( $rules ) {
				$param->applyRules ( $rules );
			}, in_array ( 'required', $rules ) );
		}
		
		return $this;
	}
	
	
	/**
	 * @return ValidationRuleParser
	 */
	protected function getValidationRuleParser ()
	{
		if ( is_null ( $this->ruleParser ) )
		{
			$this->ruleParser = new ValidationRuleParser( [] );
		}
		return $this->ruleParser;
	}
	
}