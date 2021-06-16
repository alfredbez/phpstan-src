<?php declare(strict_types = 1);

namespace PHPStan\Type;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassMemberAccessAnswerer;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ConstantReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\Php\ClosureCallUnresolvedMethodPrototypeReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\Type\UnresolvedMethodPrototypeReflection;
use PHPStan\Reflection\Type\UnresolvedPropertyPrototypeReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Traits\NonGenericTypeTrait;
use PHPStan\Type\Traits\UndecidedComparisonTypeTrait;

/** @api */
class ClosureType implements TypeWithClassName, ParametersAcceptor
{

	use NonGenericTypeTrait;
	use UndecidedComparisonTypeTrait;

	private ObjectType $objectType;

	/** @var array<int, \PHPStan\Reflection\ParameterReflection> */
	private array $parameters;

	private Type $returnType;

	private bool $variadic;

	/**
	 * @api
	 * @param array<int, \PHPStan\Reflection\ParameterReflection> $parameters
	 * @param Type $returnType
	 * @param bool $variadic
	 */
	public function __construct(
		array $parameters,
		Type $returnType,
		bool $variadic
	)
	{
		$this->objectType = new ObjectType(\Closure::class);
		$this->parameters = $parameters;
		$this->returnType = $returnType;
		$this->variadic = $variadic;
	}

	public function getClassName(): string
	{
		return $this->objectType->getClassName();
	}

	public function getClassReflection(): ?ClassReflection
	{
		return $this->objectType->getClassReflection();
	}

	public function getAncestorWithClassName(string $className): ?TypeWithClassName
	{
		return $this->objectType->getAncestorWithClassName($className);
	}

	/**
	 * @return string[]
	 */
	public function getReferencedClasses(): array
	{
		$classes = $this->objectType->getReferencedClasses();
		foreach ($this->parameters as $parameter) {
			$classes = array_merge($classes, $parameter->getType()->getReferencedClasses());
		}

		return array_merge($classes, $this->returnType->getReferencedClasses());
	}

	public function accepts(Type $type, bool $strictTypes): TrinaryLogic
	{
		if ($type instanceof CompoundType) {
			return CompoundTypeHelper::accepts($type, $this, $strictTypes);
		}

		if (!$type instanceof ClosureType) {
			return $this->objectType->accepts($type, $strictTypes);
		}

		return $this->isSuperTypeOfInternal($type, true);
	}

	public function isSuperTypeOf(Type $type): TrinaryLogic
	{
		return $this->isSuperTypeOfInternal($type, false);
	}

	private function isSuperTypeOfInternal(Type $type, bool $treatMixedAsAny): TrinaryLogic
	{
		if ($type instanceof self) {
			return CallableTypeHelper::isParametersAcceptorSuperTypeOf(
				$this,
				$type,
				$treatMixedAsAny
			);
		}

		if (
			$type instanceof TypeWithClassName
			&& $type->getClassName() === \Closure::class
		) {
			return TrinaryLogic::createMaybe();
		}

		return $this->objectType->isSuperTypeOf($type);
	}

	public function equals(Type $type): bool
	{
		if (!$type instanceof self) {
			return false;
		}

		return $this->returnType->equals($type->returnType);
	}

	public function describe(VerbosityLevel $level): string
	{
		return $level->handle(
			static function (): string {
				return 'Closure';
			},
			function () use ($level): string {
				return sprintf(
					'Closure(%s): %s',
					implode(', ', array_map(static function (ParameterReflection $parameter) use ($level): string {
						return sprintf('%s%s', $parameter->isVariadic() ? '...' : '', $parameter->getType()->describe($level));
					}, $this->parameters)),
					$this->returnType->describe($level)
				);
			}
		);
	}

	public function canAccessProperties(): TrinaryLogic
	{
		return $this->objectType->canAccessProperties();
	}

	public function hasProperty(string $propertyName): TrinaryLogic
	{
		return $this->objectType->hasProperty($propertyName);
	}

	public function getProperty(string $propertyName, ClassMemberAccessAnswerer $scope): PropertyReflection
	{
		return $this->objectType->getProperty($propertyName, $scope);
	}

	public function getUnresolvedPropertyPrototype(string $propertyName, ClassMemberAccessAnswerer $scope): UnresolvedPropertyPrototypeReflection
	{
		return $this->objectType->getUnresolvedPropertyPrototype($propertyName, $scope);
	}

	public function canCallMethods(): TrinaryLogic
	{
		return $this->objectType->canCallMethods();
	}

	public function hasMethod(string $methodName): TrinaryLogic
	{
		return $this->objectType->hasMethod($methodName);
	}

	public function getMethod(string $methodName, ClassMemberAccessAnswerer $scope): MethodReflection
	{
		return $this->getUnresolvedMethodPrototype($methodName, $scope)->getTransformedMethod();
	}

	public function getUnresolvedMethodPrototype(string $methodName, ClassMemberAccessAnswerer $scope): UnresolvedMethodPrototypeReflection
	{
		if ($methodName === 'call') {
			return new ClosureCallUnresolvedMethodPrototypeReflection(
				$this->objectType->getUnresolvedMethodPrototype($methodName, $scope),
				$this
			);
		}

		return $this->objectType->getUnresolvedMethodPrototype($methodName, $scope);
	}

	public function canAccessConstants(): TrinaryLogic
	{
		return $this->objectType->canAccessConstants();
	}

	public function hasConstant(string $constantName): TrinaryLogic
	{
		return $this->objectType->hasConstant($constantName);
	}

	public function getConstant(string $constantName): ConstantReflection
	{
		return $this->objectType->getConstant($constantName);
	}

	public function isIterable(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function isIterableAtLeastOnce(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getIterableKeyType(): Type
	{
		return new ErrorType();
	}

	public function getIterableValueType(): Type
	{
		return new ErrorType();
	}

	public function isOffsetAccessible(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function hasOffsetValueType(Type $offsetType): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getOffsetValueType(Type $offsetType): Type
	{
		return new ErrorType();
	}

	public function setOffsetValueType(?Type $offsetType, Type $valueType): Type
	{
		return new ErrorType();
	}

	public function isCallable(): TrinaryLogic
	{
		return TrinaryLogic::createYes();
	}

	/**
	 * @param \PHPStan\Reflection\ClassMemberAccessAnswerer $scope
	 * @return \PHPStan\Reflection\ParametersAcceptor[]
	 */
	public function getCallableParametersAcceptors(ClassMemberAccessAnswerer $scope): array
	{
		return [$this];
	}

	public function isCloneable(): TrinaryLogic
	{
		return TrinaryLogic::createYes();
	}

	public function toBoolean(): BooleanType
	{
		return new ConstantBooleanType(true);
	}

	public function toNumber(): Type
	{
		return new ErrorType();
	}

	public function toInteger(): Type
	{
		return new ErrorType();
	}

	public function toFloat(): Type
	{
		return new ErrorType();
	}

	public function toString(): Type
	{
		return new ErrorType();
	}

	public function toArray(): Type
	{
		return new ConstantArrayType(
			[new ConstantIntegerType(0)],
			[$this],
			1
		);
	}

	public function getTemplateTypeMap(): TemplateTypeMap
	{
		return TemplateTypeMap::createEmpty();
	}

	public function getResolvedTemplateTypeMap(): TemplateTypeMap
	{
		return TemplateTypeMap::createEmpty();
	}

	/**
	 * @return array<int, \PHPStan\Reflection\ParameterReflection>
	 */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	public function isVariadic(): bool
	{
		return $this->variadic;
	}

	public function getReturnType(): Type
	{
		return $this->returnType;
	}

	public function inferTemplateTypes(Type $receivedType): TemplateTypeMap
	{
		if ($receivedType instanceof UnionType || $receivedType instanceof IntersectionType) {
			return $receivedType->inferTemplateTypesOn($this);
		}

		if ($receivedType->isCallable()->no()) {
			return TemplateTypeMap::createEmpty();
		}

		$parametersAcceptors = $receivedType->getCallableParametersAcceptors(new OutOfClassScope());

		$typeMap = TemplateTypeMap::createEmpty();

		foreach ($parametersAcceptors as $parametersAcceptor) {
			$typeMap = $typeMap->union($this->inferTemplateTypesOnParametersAcceptor($receivedType, $parametersAcceptor));
		}

		return $typeMap;
	}

	private function inferTemplateTypesOnParametersAcceptor(Type $receivedType, ParametersAcceptor $parametersAcceptor): TemplateTypeMap
	{
		$typeMap = TemplateTypeMap::createEmpty();
		$args = $parametersAcceptor->getParameters();
		$returnType = $parametersAcceptor->getReturnType();

		foreach ($this->getParameters() as $i => $param) {
			$argType = isset($args[$i]) ? $args[$i]->getType() : new NeverType();
			$paramType = $param->getType();
			$typeMap = $typeMap->union($paramType->inferTemplateTypes($argType)->convertToLowerBoundTypes());
		}

		return $typeMap->union($this->getReturnType()->inferTemplateTypes($returnType));
	}

	public function traverse(callable $cb): Type
	{
		return new self(
			array_map(static function (ParameterReflection $param) use ($cb): NativeParameterReflection {
				$defaultValue = $param->getDefaultValue();
				return new NativeParameterReflection(
					$param->getName(),
					$param->isOptional(),
					$cb($param->getType()),
					$param->passedByReference(),
					$param->isVariadic(),
					$defaultValue !== null ? $cb($defaultValue) : null
				);
			}, $this->getParameters()),
			$cb($this->getReturnType()),
			$this->isVariadic()
		);
	}

	public function isArray(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function isNumericString(): TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	/**
	 * @param mixed[] $properties
	 * @return Type
	 */
	public static function __set_state(array $properties): Type
	{
		return new self(
			$properties['parameters'],
			$properties['returnType'],
			$properties['variadic']
		);
	}

}
