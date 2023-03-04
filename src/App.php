<?php

namespace Bottledcode\SwytchFramework;

use Bottledcode\SwytchFramework\CacheControl\Queue;
use Bottledcode\SwytchFramework\Language\LanguageAcceptor;
use Bottledcode\SwytchFramework\Router\Exceptions\InvalidRequest;
use Bottledcode\SwytchFramework\Router\Exceptions\NotAuthorized;
use Bottledcode\SwytchFramework\Router\MagicRouter;
use Bottledcode\SwytchFramework\Template\Interfaces\StateProviderInterface;
use Bottledcode\SwytchFramework\Template\ReferenceImplementation\ValidatedState;
use DI\ContainerBuilder;
use DI\Definition\Helper\DefinitionHelper;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
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
	 * @param string $indexClass
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
		$this->setHeader('Vary', 'Accept-Language, Accept-Encoding, Accept');
		$this->setHeader('X-Frame-Options', 'DENY');
		$this->setHeader('X-Content-Type-Options', 'nosniff');

		$this->createContainer();

		try {
			/**
			 * @var LanguageAcceptor $language
			 */
			$language = $this->container->get(LanguageAcceptor::class);
			$language->loadLanguage();
			$this->setHeader('Content-Language', $language->currentLanguage);
			$router = new MagicRouter($this->container, $this->indexClass);
			$response = $router->go();

			if($response === null) {
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

	protected function setHeader(string $name, string $value): void
	{
		if (!headers_sent()) {
			header($name . ': ' . $value);
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
			'req.ACCEPT_LANGUAGE' => fn() => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? get('env.DEFAULT_LANGUAGE'),
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
			...$this->dependencyInjection,
		]);

		if (!$this->debug) {
			$builder->enableCompilation('/tmp');
		}

		return $this->container = $builder->build();
	}
}
