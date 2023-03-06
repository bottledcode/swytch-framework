<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\CacheControl\Queue;
use Bottledcode\SwytchFramework\Hooks\Api\Authorization;
use Bottledcode\SwytchFramework\Hooks\Api\Invoker;
use Bottledcode\SwytchFramework\Hooks\Api\Router;
use Bottledcode\SwytchFramework\Hooks\Common\Determinator;
use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Hooks\Html\HeadTagFilter;
use Bottledcode\SwytchFramework\Hooks\Html\Renderer;
use Bottledcode\SwytchFramework\Language\LanguageAcceptor;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Router\Exceptions\NotAuthorized;
use Bottledcode\SwytchFramework\Router\MagicRouter;
use Bottledcode\SwytchFramework\Template\Escapers\Variables;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Bottledcode\SwytchFramework\Template\ReferenceImplementation\ValidatedState;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use function DI\create;
use function DI\get;

class App
{
	protected ContainerInterface|null $container = null;

	/**
	 * @param bool $debug
	 * @param class-string $indexClass
	 * @param array<array-key, callable-string|callable|DefinitionHelper> $dependencyInjection
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		protected bool $debug,
		protected string $indexClass,
		protected array $dependencyInjection = [],
		bool $registerErrorHandler = true
	) {
		if ($registerErrorHandler) {
			set_error_handler(function ($errno, $errstr, $errfile, $errline): bool {
				if (!(error_reporting() & $errno)) {
					// This error code is not included in error_reporting
					$this->container?->get(LoggerInterface::class)->debug(
						'Error suppressed',
						compact('errno', 'errstr', 'errfile', 'errline')
					);
					return true;
				}
				http_response_code(500);
				$this->container?->get(LoggerInterface::class)->critical(
					'Fatal error',
					compact('errno', 'errstr', 'errfile', 'errline')
				);
				die();
			});
		}
	}

	public function run(): void
	{
		$this->createContainer();

		/**
		 * @var Headers $headers
		 */
		$headers = $this->container->get(Headers::class);
		$headers->setHeader('Vary', 'Accept-Language, Accept-Encoding, Accept');
		$headers->setHeader('X-Frame-Options', 'DENY');
		$headers->setHeader('X-Content-Type-Options', 'nosniff');

		try {
			$router = new MagicRouter($this->container, $this->indexClass);
			$response = $router->go();

			/**
			 * @var EmitterInterface $emitter
			 */
			$emitter = $this->container->get(EmitterInterface::class);

			if ($response === null) {
				http_response_code(404);
				return;
			}

			/**
			 * @var Queue $caching
			 */
			$caching = $this->container->get(Queue::class);
			$cacheResult = $caching->getSortedQueue()[0] ?? null;

			if (!empty($cacheResult) && method_exists($cacheResult, 'render')) {
				if ($this->debug) {
					$this->setHeader('Cache-Tag-Rendered', $cacheResult->tag);
				}
				$cacheResult->render($router->lastEtag);
				if ($cacheResult->etagRequired && !empty($router->lastEtag)) {
					http_response_code(304);
					return;
				}
			}

			$this->setHeader('Content-Length', (string)strlen($response));

			echo $response;
		} catch (InvalidRequest $e) {
			http_response_code(400);
			$this->container->get(LoggerInterface::class)->warning('Invalid request', ['exception' => $e]);
			return;
		} catch (NotAuthorized $e) {
			http_response_code(401);
			$this->container->get(LoggerInterface::class)->warning('Not authorized', ['exception' => $e]);
			return;
		}
	}

	protected function createContainer(): ContainerInterface
	{
		$builder = new ContainerBuilder();
		$builder->addDefinitions([
			'env.SWYTCH_STATE_SECRET' => fn() => getenv('SWYTCH_STATE_SECRET') ?: throw new \RuntimeException(
				'Missing STATE_SECRET env var'
			),
			'env.SWYTCH_DEFAULT_LANGUAGE' => fn() => getenv('SWYTCH_DEFAULT_LANGUAGE') ?: 'en',
			'env.SWYTCH_SUPPORTED_LANGUAGES' => fn() => explode(',', getenv('SWYTCH_SUPPORTED_LANGUAGES') ?: 'en'),
			'env.SWYTCH_LANGUAGE_DIR' => fn() => getenv('SWYTCH_LANGUAGE_DIR') ?: __DIR__ . '/Language',
			'req.ACCEPT_LANGUAGE' =>
				fn(ContainerInterface $c) => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $c->get('env.SWYTCH_DEFAULT_LANGUAGE'),
			'app.root' => $this->indexClass,
			StateProviderInterface::class => create(ValidatedState::class)
				->constructor(get('env.SWYTCH_STATE_SECRET'), get(Serializer::class)),
			Serializer::class => create(Serializer::class)
				->constructor([
					get(ArrayDenormalizer::class),
					get(BackedEnumNormalizer::class),
					get(ObjectNormalizer::class),
				]),
			LoggerInterface::class => create(NullLogger::class),
			LanguageAcceptor::class => create(LanguageAcceptor::class)
				->constructor(
					get('req.ACCEPT_LANGUAGE'),
					get('env.SWYTCH_SUPPORTED_LANGUAGES'),
					get('env.SWYTCH_LANGUAGE_DIR')
				),
			Renderer::class => create(Renderer::class)->method('setRoot', get('app.root')),
			LifecyleHooks::class => static fn(
				Variables $escaper,
				Headers $headers,
				LanguageAcceptor $languageAcceptor,
				Router $router,
				Authorization $authorization,
				Invoker $invoker,
				Determinator $determinator,
				HeadTagFilter $headTagFilter,
			) => (new LifecyleHooks(
				$escaper
			))
				->determineTypeWith($determinator, 10)
				->preprocessWith($languageAcceptor, 10)
				->preprocessWith($router, 10)
				->preprocessWith($authorization, 10)
				->processWith($invoker, 10)
				->postprocessWith($headers, 10)
				->postprocessWith($headTagFilter, 10),
			Headers::class => create(Headers::class),
			Psr17Factory::class => create(Psr17Factory::class),
			ServerRequestFactoryInterface::class => get(Psr17Factory::class),
			UriFactoryInterface::class => get(Psr17Factory::class),
			UploadedFileFactoryInterface::class => get(Psr17Factory::class),
			StreamFactoryInterface::class => get(Psr17Factory::class),
			ServerRequestCreatorInterface::class => create(ServerRequestCreator::class),
			ResponseInterface::class => fn(Psr17Factory $factory) => $factory->createResponse(),
			EmitterInterface::class => get(SapiEmitter::class),
			...$this->dependencyInjection,
		]);

		if (!$this->debug) {
			$builder->enableCompilation('/tmp');
		}

		return $this->container = $builder->build();
	}
}
