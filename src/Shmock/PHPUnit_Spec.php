<?php
namespace Shmock;

/**
 * A spec is used during the build phase of mock creation to configure the expectations
 * on a single invocation of a method on a mock.
 *
 * <pre>
 *  // return_value() and twice() are defined on PHPUnit_Spec
 *  $calc->add(1,2)->return_value(3)->twice();
 * </pre>
 */
class PHPUnit_Spec
{

    /**
     * @var \PHPUnit_Framework_TestCase
     * @internal
     */
    private $test_case = null;

    /**
     * @internal the method being invoked
     */
    private $method = null;

    /**
     * @internal captures the parameters to the invocation
     */
    private $with = null;

    /**
     * @internal captures the call to times()
     */
    private $times = 1;

    /**
     * @internal captures the closure to will()
     */
    private $will = null;

    /**
     * @var boolean
     * @internal captures the order_matters invocation
     */
    private $order_matters = null;

    /**
     * @internal when order_matters is set, this value
     * informs PHPUnit of this call's position in the set
     * of expected operations on the mock.
     */
    private $call_index = null;

    /**
     * @var boolean
     * @internal whether at_least_once has been set.
     */
    private $at_least_once = false;

    /**
     * @var boolean
     * @internal whether return_this has been set
     */
    private $return_this = false;

    /**
     * @internal the sets of all parameters used
     * on this method.
     */
    private $parameter_sets = []; // for policies

    /**
     * @internal the set of returned values from this
     * method
     */
    private $returned_values = []; // for policies

    /**
     * @internal the exceptions expected to be thrown
     * from this method.
     */
    private $thrown_exceptions = []; // for policies

    /**
     * This class is not directly instantiated,
     * but instead returned every time you invoke a method on a \Shmock\Shmock_Instance or
     * \Shmock\Shmock_Class.
     *
     * Specs in Shmock make the assumption that because you are mocking the method that it will
     * be invoked at least once. This is a default setting on the mock and can be changed by
     * specifying a number of times to invoke the method, as done above with the <code>twice()</code> method.
     * Callers intending to invoke methods more than once with different arguments are directed to
     * the <code>return_value_map</code> facility or to specify <code>$mock->order_matters();</code>.
     *
     * A few options on specs can be in conflict with each other. For example, you may elect to
     * return a value to the caller via <code>return_value($v)</code>, throw an exception via <code>throw_exception($e)</code>
     * or to perform a custom action via <code>will(function () {})</code>. In cases of a conflict, last expectation
     * set wins.
     *
     * PHPUnit specs also accept standar <code>\PHPUnit_Framework_Constraint</code> instances as arguments,
     * so wildcard arguments or other helpers from PHPUnit are available for use:
     *
     * <pre>
     *  $calc->add($this->greaterThan(1), $this->lessThan(2));
     * </pre>
     * @see PHPUnit_Spec::will() See will() to learn how to trigger arbitrary behavior on invocation.
     * @see PHPUnit_Spec::return_value_map() See return_value_map() to easily allow order-insensitive invocation of the same method.
     */
    public function __construct($test, $shmock, $method, $with, $order_matters, $call_index)
    {
        $this->test_case = $test;
        $this->method = $method;
        $this->with = $with;
        if ($with) {
            $this->parameter_sets[] = $with;
        }
        $this->order_matters = $order_matters;
        $this->call_index = $call_index;
    }

    /**
     * Specify that the method will be invoked $times times.
     * <pre>
     *  // expect notify will be called 5 times
     *  $shmock->notify()->times(5);
     * </pre>
     *
     * @param  int                  $times the number of times to expect the given call
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::at_least_once() See at_least_once()
     */
    public function times($times)
    {
        $this->times = $times;

        return $this;
    }

    /**
     * Specify that the method will be invoked once.
     *
     * This is a shorthand for <code>times(1)</code>
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::times() See times()
     */
    public function once()
    {
        return $this->times(1);
    }

    /**
     * Specify that the method will be invoked twice.
     *
     * This is a shorthand for <code>times(2)</code>
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::times() See times()
     */
    public function twice()
    {
        return $this->times(2);
    }

    /**
     * Specifies that the number of invocations of this method
     * is not to be verified by Shmock.
     * <pre>
     *  $shmock->notify()->any();
     * </pre>
     * This is a shorthand for <code>times(null)</code>
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::times() See times()
     */
    public function any()
    {
        return $this->times(null);
    }

    /**
     * Specifies that this method is never to be invoked.
     *
     * This is an alias for <code>times(0)</code>
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::times() See times()
     */
    public function never()
    {
        return $this->times(0);
    }

    /**
     * Specifies that the method is to be invoked at least once
     * but possibly more.
     * This directive is only respected if no other calls to <code>times()</code> have been recorded.
     * <pre>
     *  $shmock->notify()->at_least_once();
     * </pre>
     *
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\PHPUnit_Spec::times() See times()
     */
    public function at_least_once()
    {
        $this->at_least_once = true;

        return $this;
    }

    /**
     * Specifies that the given closure will be executed on invocation.
     * The first argument to the closure is an instance of <code>\PHPUnit_Framework_MockObject_Invocation</code>.
     *
     * <pre>
     *  // custom action with a closure
     *
     *  $shmock->notify()->will(function ($invocation) {
     *    $this->assertTrue(count($invocation->parameters) > 2);
     *  });
     * </pre>
     *
     * @param  callable             $will_closure
     * @return \Shmock\PHPUnit_Spec
     */
    public function will($will_closure)
    {
        $this->will = $will_closure;

        return $this;
    }

    /**
    * An order-agnostic set of return values given a set of inputs.
    *
    * @param mixed[][] an array of arrays of arguments with the final value
    * of the array being the return value.
    *
    * For example, if you were simulating addition:
    *
    * $shmock_calculator->add()->return_value_map([
    * 	[1, 2, 3], // 1 + 2 = 3
    * 	[10, 15, 25],
    * 	[11, 11, 22]
    * ]);
    *
    */
    public function return_value_map($map_of_args_to_values)
    {
        $limit = count($map_of_args_to_values);
        $this->test_case->assertGreaterThan(0, $limit, 'Must specify at least one return value');
        $this->times($limit);

        $stub = new \PHPUnit_Framework_MockObject_Stub_ReturnValueMap($map_of_args_to_values);

        foreach ($map_of_args_to_values as $params_and_return) {
            $this->parameter_sets[] = array_slice($params_and_return, 0, count($params_and_return) - 1);
            $this->returned_values[] = $params_and_return[count($params_and_return) - 1];
        }

        return $this->will(function ($invocation) use ($stub) {
            return $stub->invoke($invocation);
        });
    }

    /**
     * Specifies that the method will return true.
     * This is a shorthand for <code>return_value(true)</code>
     * @return \Shmock\PHPUnit_Spec
     */
    public function return_true()
    {
        return $this->return_value(true);
    }

    /**
     * Specifies that the method will return false.
     * This is a shorthand for <code>return_value(false)</code>
     * @return \Shmock\PHPUnit_Spec
     */
    public function return_false()
    {
        return $this->return_value(false);
    }

    /**
    * Specifies that the method will return null.
    * This is a shorthand for <code>return_value(null)</code>
    * @return \Shmock\PHPUnit_Spec
    */
    public function return_null()
    {
        return $this->return_value(null);
    }

    /**
     * Specifies that the method will return the given value on invocation.
     * <pre>
     *  $shmock->notify()->return_value("notification!");
     * </pre>
     * @param  mixed|null           $value The value to return on invocation
     * @return \Shmock\PHPUnit_Spec
     * @see \Shmock\Shmock_Instance::order_matters() If you wish to specify multiple return values and the order is important, look at Shmock_Instance::order_matters()
     * @see \Shmock\PHPUnit_Spec::return_value_map() If you wish to specify multiple return values contingent on the parameters, but otherwise insensitive to the order, look at return_value_map()
     */
    public function return_value($value)
    {
        $this->returned_values[] = $value;

        return $this->will(function () use ($value) {
            return $value;
        });
    }

    public function return_this()
    {
        $this->return_this = true;
    }

    public function throw_exception($e=null)
    {
        $this->thrown_exceptions[] = $e ?: new \Exception();

        return $this->will(function () use ($e) {
            if (!$e) {
                $e = new Shmock_Exception();
            }
            throw $e;
        });
    }

    public function return_consecutively($array_of_values, $keep_returning_last_value=false)
    {
        $this->returned_values = array_merge($this->returned_values, $array_of_values);
        $this->will(function () use ($array_of_values, $keep_returning_last_value) {
            static $counter = -1;
            $counter++;
            if ($counter == count($array_of_values)) {
                if ($keep_returning_last_value) {
                    return $array_of_values[count($array_of_values)-1];
                }
            } else {
                return $array_of_values[$counter];
            }
        });
        if (!$keep_returning_last_value) {
            $this->times(count($array_of_values));
        }

        return $this;
    }

    public function return_shmock($class, $shmock_closure=null)
    {
        $test_case = $this->test_case;
        if ($shmock_closure) {
            return $this->return_value(Shmock::create($test_case, $class, $shmock_closure));
        } else {
            return $this;
        }
    }

    /**
    * @param mixed $mock
    * @param \Shmock\Policy[] $policies
    * @param boolean $static
    */
    public function finalize_expectations($mock, array $policies, $static, $class)
    {
        $test_case = $this->test_case;

        foreach ($policies as $policy) {
            foreach ($this->returned_values as $returned_value) {
                $policy->check_method_return_value($class, $this->method, $returned_value, $static);
            }
            foreach ($this->thrown_exceptions as $thrown) {
                $policy->check_method_throws($class, $this->method, $thrown, $static);
            }
            foreach ($this->parameter_sets as $parameter_set) {
                $policy->check_method_parameters($class, $this->method, $parameter_set, $static);
            }
        }

        if ($this->times === null) {
            if ($static) {
                $builder = $mock::staticExpects($test_case->any());
            } else {
                $builder = $mock->expects($test_case->any());
            }
        } elseif ($this->order_matters) {
            if ($static) {
                $builder = $mock::staticExpects($test_case->at($this->call_index));
            } else {
                $builder = $mock->expects($test_case->at($this->call_index));
            }
        } elseif ($this->at_least_once) {
            if ($static) {
                $builder = $mock::staticExpects($test_case->atLeastOnce());
            } else {
                $builder = $mock->expects($test_case->atLeastOnce());
            }
        } else {
            if ($static) {
                $builder = $mock::staticExpects($test_case->exactly($this->times));
            } else {
                $builder = $mock->expects($test_case->exactly($this->times));
            }
        }

        $builder->method($this->method);

        if ($this->with) {
            $function = new \ReflectionMethod(get_class($builder),'with');
            $function->invokeargs($builder, $this->with);

        }

        if ($this->return_this) {
            if ($this->will) {
                throw new \InvalidArgumentException("You cannot specify return_this with another will() operation like return_value or throw_exception");
            } else {
                $this->return_value($mock);
            }
        }

        if ($this->will) {
            $builder->will(new Shmock_Closure_Invoker($this->will));
        }
    }
}
