<?php

namespace UnusedPrivateMethodCallableArrays;

class BigContainer
{

	public static function statusKey(): void
	{
	}

}

class Foo
{

	private static function doSelf(): void
	{
	}

	private static function doStatic(): void
	{
	}

	private static function doClassName(): void
	{
	}

	private static function doString(): void
	{
	}

	private function doObject(): void
	{
	}

	private function doNewSelf(): void
	{
	}

	private static function statusKey(): void
	{
	}

	public function run(Foo $foo): void
	{
		$a = [self::class, 'doSelf'];
		$b = [static::class, 'doStatic'];
		$c = [Foo::class, 'doClassName'];
		$d = ['UnusedPrivateMethodCallableArrays\Foo', 'doString'];
		$e = [$foo, 'doObject'];
		$f = [new self(), 'doNewSelf'];

		$data = [BigContainer::class, 'statusKey'];
		$data2 = ['UnusedPrivateMethodCallableArrays\BigContainer', 'statusKey'];
		$data3 = [new BigContainer(), 'statusKey'];
	}

}
