<?php

namespace Bug15494;

class BigContainer
{
}

class Service
{

	private function unusedMethod(): void
	{
	}

	private static function usedMethod(): void
	{
	}

	public function process(string $item): bool
	{
		$modes = [BigContainer::class, 'packstation'];
		$named = ['Bug15494External\HeavyContainer', 'packstation'];
		$used = [self::class, 'usedMethod'];

		return in_array($item, $modes, true);
	}

}
