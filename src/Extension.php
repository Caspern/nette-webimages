<?php

/**
 * Copyright (c) dotBlue (http://dotblue.net)
 */

namespace DotBlue\WebImages;

use Nette\DI;


class Extension extends DI\CompilerExtension
{

	const FORMAT_JPEG = 'jpeg';
	const FORMAT_PNG = 'png';
	const FORMAT_GIF = 'gif';

	/** @var array */
	private $defaults = [
		'routes' => [],
		'rules' => [],
		'providers' => [],
		'wwwDir' => '%wwwDir%',
		'format' => self::FORMAT_JPEG,
	];

	/** @var array */
	public $supportedFormats = [
		self::FORMAT_JPEG => Generator::FORMAT_JPEG,
		self::FORMAT_PNG => Generator::FORMAT_PNG,
		self::FORMAT_GIF => Generator::FORMAT_GIF,
	];



	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$validator = $container->addDefinition($this->prefix('validator'))
			->setClass('DotBlue\WebImages\Validator');

		$generator = $container->addDefinition($this->prefix('generator'))
			->setClass('DotBlue\WebImages\Generator', [
				$config['wwwDir'],
			]);

		foreach ($config['rules'] as $rule) {
			$validator->addSetup('$service->addRule(?, ?, ?)', [
				$rule['width'],
				$rule['height'],
				isset($rule['algorithm']) ? $rule['algorithm'] : NULL,
			]);
		}

		$i = 0;
		foreach ($config['routes'] as $route => $definition) {
			if (!is_array($definition)) {
				$route = $definition;
				$format = $config['format'];
				$defaults = [];
			} else {
				$route = $definition['mask'];
				$format = isset($definition['format']) ? $definition['format'] : $config['format'];
				$defaults = isset($definition['defaults']) ? $definition['defaults'] : [];
			}

			$route = $container->addDefinition($this->prefix('route' . $i))
				->setClass('DotBlue\WebImages\Route', [
					$route,
					$format,
					$defaults,
					$this->prefix('@generator'),
				])
				->setAutowired(FALSE);

			$container->getDefinition('router')
				->addSetup('$service[] = ?', [
					$this->prefix('@route' . $i),
				]);

			$i++;
		}

		if (count($config['providers']) === 0) {
			throw new InvalidConfigException("You have to register at least one IProvider in '" . $this->prefix('providers') . "' directive.");
		}

		foreach ($config['providers'] as $name => $provider) {
			$this->compiler->parseServices($container, [
				'services' => [$this->prefix('provider' . $name) => $provider],
			]);
			$generator->addSetup('addProvider', [$this->prefix('@provider' . $name)]);
		}

		$latte = $container->getDefinition('nette.latteFactory');
		$latte->addSetup('DotBlue\WebImages\Macros::install(?->getCompiler())', ['@self']);
	}

}

class InvalidConfigException extends \Exception {}
