<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\Hooks\Common\Headers;
use Bottledcode\SwytchFramework\Hooks\Html\HeadTagFilter;
use Bottledcode\SwytchFramework\Hooks\Html\Renderer;
use Bottledcode\SwytchFramework\Language\LanguageAcceptor;
use Bottledcode\SwytchFramework\Router\MagicRouter;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Bottledcode\SwytchFramework\Template\ReferenceImplementation\ValidatedState;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;
use Exception;
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
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

use function DI\autowire;
use function DI\get;

/**
 * This is the main entrypoint for the application. It can be overridden and extended for customization. It is
 * responsible for configuration of the app container and for running the application.
 */
class App
{
	protected ContainerInterface|null $container = null;

	/**
	 * @param bool $debug
	 * @param class-string $indexClass
	 * @param array<array-key, callable-string|callable|DefinitionHelper> $dependencyInjection
	 * @param bool $registerErrorHandler
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function __construct(
		protected bool $debug,
		protected string $indexClass,
		protected array|ContainerInterface $dependencyInjection = [],
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
		$this->createContainer();
	}

	/**
	 * Creates the application container.
	 * @return ContainerInterface
	 * @throws Exception
	 */
	protected function createContainer(): ContainerInterface
	{
		$builder = new ContainerBuilder();
		$builder->addDefinitions([
			'env.SWYTCH_STATE_SECRET' => fn() => getenv('SWYTCH_STATE_SECRET') ?: throw new RuntimeException(
				'Missing STATE_SECRET env var'
			),
			'env.SWYTCH_DEFAULT_LANGUAGE' => fn() => getenv('SWYTCH_DEFAULT_LANGUAGE') ?: 'en',
			'env.SWYTCH_SUPPORTED_LANGUAGES' => fn() => explode(',', getenv('SWYTCH_SUPPORTED_LANGUAGES') ?: 'en'),
			'env.SWYTCH_LANGUAGE_DIR' => fn() => getenv('SWYTCH_LANGUAGE_DIR') ?: __DIR__ . '/Language',
			'req.ACCEPT_LANGUAGE' =>
				fn(ContainerInterface $c) => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? $c->get('env.SWYTCH_DEFAULT_LANGUAGE'),
			'app.root' => $this->indexClass,
			StateProviderInterface::class => autowire(ValidatedState::class)
				->constructor(get('env.SWYTCH_STATE_SECRET'), get(Serializer::class)),
			Serializer::class => autowire(Serializer::class)
				->constructor([
					get(ArrayDenormalizer::class),
					get(BackedEnumNormalizer::class),
					get(ObjectNormalizer::class),
				]),
			LoggerInterface::class => autowire(NullLogger::class),
			LanguageAcceptor::class => autowire(LanguageAcceptor::class)
				->constructor(
					get('req.ACCEPT_LANGUAGE'),
					get('env.SWYTCH_SUPPORTED_LANGUAGES'),
					get('env.SWYTCH_LANGUAGE_DIR')
				),
			Renderer::class => autowire(Renderer::class)->method('setRoot', get('app.root')),
			LifecyleHooks::class => autowire(LifecyleHooks::class),
			Headers::class => autowire(Headers::class),
			Psr17Factory::class => autowire(Psr17Factory::class),
			ServerRequestFactoryInterface::class => autowire(Psr17Factory::class),
			UriFactoryInterface::class => autowire(Psr17Factory::class),
			UploadedFileFactoryInterface::class => autowire(Psr17Factory::class),
			StreamFactoryInterface::class => autowire(Psr17Factory::class),
			ServerRequestCreatorInterface::class => autowire(ServerRequestCreator::class),
			ResponseInterface::class => fn(Psr17Factory $factory) => $factory->createResponse(),
			EmitterInterface::class => autowire(SapiEmitter::class),
			...(is_array($this->dependencyInjection) ? $this->dependencyInjection : []),
		]);

		!is_array($this->dependencyInjection) && $builder->wrapContainer($this->dependencyInjection);

		if (!$this->debug) {
			$builder->enableCompilation('/tmp');
		}

		return $this->container = $builder->build();
	}

	/**
	 * Runs the application.
	 *
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	public function run(): void
	{
		/**
		 * @var Headers $headers
		 */
		$headers = $this->container->get(Headers::class);
		$headers->setHeader('Vary', 'Accept-Language, Accept-Encoding, Accept');
		$headers->setHeader('X-Frame-Options', 'DENY');
		$headers->setHeader('X-Content-Type-Options', 'nosniff');

		/**
		 * @var HeadTagFilter $htmlHeader
		 */
		$htmlHeader = $this->container->get(HeadTagFilter::class);
		$htmlHeader->addScript('htmx', 'https://unpkg.com/htmx.org@1.8.5', defer: true);

		$router = new MagicRouter($this->container);
		$response = $router->go();

		/**
		 * @var EmitterInterface $emitter
		 */
		$emitter = $this->container->get(EmitterInterface::class);
		$emitter->emit($response);

		flush();
	}

	/**
	 * @return ContainerInterface The application container.
	 */
	public function getContainer(): ContainerInterface
	{
		return $this->container;
	}
}
