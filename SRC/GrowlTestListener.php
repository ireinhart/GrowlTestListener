<?php

class PHPUnit_Extensions_TestListener_GrowlTestListener
implements PHPUnit_Framework_TestListener
{
    const TEST_RESULT_COLOR_RED = 'red';
    const TEST_RESULT_COLOR_YELLOW = 'yellow';
    const TEST_RESULT_COLOR_GREEN = 'green';
    
    private $_errors = array();
    private $_failures = array();
    private $_incompletes = array();
    private $_skips = array();
    private $_tests = array();
    private $_suites = array();
    private $_endedSuites = 0;
    private $_assertionCount = 0;
    private $_startTime = 0;

    private $_successPicturePath = null;
    private $_incompletePicturePath = null;
    private $_failurePicturePath = null;
    private $_options = array('parameter' => '');

    /**
     *
     * @param string $successPicturePath
     * @param string $incompletePicturePath
     * @param string $failurePicturePath
     * @param string $sticky
     * @param string $wait
     */
    public function __construct($successPicturePath, $incompletePicturePath, 
        $failurePicturePath, $options)
    {
        $this->_successPicturePath = $successPicturePath;
        $this->_incompletePicturePath = $incompletePicturePath;
        $this->_failurePicturePath = $failurePicturePath;
        $this->_options = $options;
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
        $this->_errors[] = $test->getName();
    }
    
    public function addFailure(PHPUnit_Framework_Test $test, 
        PHPUnit_Framework_AssertionFailedError $e, $time) 
    {     
        $this->_failures[] = $test->getName();
    }
    
    public function addIncompleteTest(PHPUnit_Framework_Test $test, 
        Exception $e, $time)
    {
        $this->_incompletes[] = $test->getName();
    }
    
    public function addSkippedTest(PHPUnit_Framework_Test $test, 
        Exception $e, $time) 
    {
        $this->_skips[] = $test->getName();
    }
    
    public function startTest(PHPUnit_Framework_Test $test)
    {

    }
    
    public function endTest(PHPUnit_Framework_Test $test, $time) 
    { 
        $this->_tests[] = array('name' => $test->getName(), 
            'assertions' => $test->getNumAssertions()
        );
        $this->_assertionCount+= $test->getNumAssertions();
    }
    
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        if (count($this->_suites) === 0) {
            PHP_Timer::start();
        }
        $this->_suites[] = $suite->getName();
    }
    
    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        $this->_endedSuites++;
        
        if (count($this->_suites) <= $this->_endedSuites)
        {
            $testTime = PHP_Timer::secondsToTimeString(
                PHP_Timer::stop());

            if ($this->_isGreenTestResult()) {
                $resultColor = self::TEST_RESULT_COLOR_GREEN;
            }
            if ($this->_isRedTestResult()) {
                $resultColor = self::TEST_RESULT_COLOR_RED;
            }
            if ($this->_isYellowTestResult()) {
                $resultColor = self::TEST_RESULT_COLOR_YELLOW;
            }

            $suiteCount = count($this->_suites);
            $testCount = count($this->_tests);
            $failureCount = count($this->_failures);
            $errorCount = count($this->_errors);
            $incompleteCount = count($this->_incompletes);
            $skipCount = count($this->_skips);

            $resultMessage = '';

            if ($suiteCount > 1) {
                $resultMessage.= "Suites: {$suiteCount}, ";
            }
            $resultMessage.= "Tests: {$testCount}, ";
            $resultMessage.= "Assertions: {$this->_assertionCount}";

            if ($failureCount > 0) {
                $resultMessage.= ", Failures: {$failureCount}";
            } 

            if ($errorCount > 0) {
                $resultMessage.= ", Errors: {$errorCount}";
            }

            if ($incompleteCount > 0) {
                $resultMessage.= ", Incompletes: {$incompleteCount}";
            }

            if ($skipCount > 0) {
                $resultMessage.= ", Skips: {$skipCount}";
            }
            $resultMessage.= " in {$testTime}.";
            $this->_growlnotify($resultColor, $resultMessage);
        }
    }

    /**
     * @param string $resultColor
     * @param string $message
     * @param string $sender The name of the application that sends the notification
     * @throws RuntimeException When growlnotify is not available
     */
    private function _growlnotify($resultColor, $message = null, $sender = 'PHPUnit')
    {
        if ($this->_isGrowlnotifyAvailable() === false) {
            throw new RuntimeException('The growlnotify tool is not available');
        }
        $notificationImage = $this->_getNotificationImageByResultColor(
            $resultColor);
        $command = "growlnotify ".$this->_options['parameter']." -m '{$message}' "
                 . "-n '{$sender}' "
                 . "-p 2 --image {$notificationImage}";
        exec($command, $response, $return);
    }

    /**
     * @return boolean
     */
    private function _isGrowlnotifyAvailable()
    {
        exec('growlnotify -v', $reponse, $status);
        return ($status === 0);
    }

    /**
     * @param string $color 
     * @return string
     */
    private function _getNotificationImageByResultColor($color)
    {
        switch ($color) {
            case self::TEST_RESULT_COLOR_RED:
                return $this->_failurePicturePath;
                break;
            case self::TEST_RESULT_COLOR_GREEN:
                return $this->_successPicturePath;
                break;
            default:
                return $this->_incompletePicturePath;
        }
    }

    /**
     * @return boolean
     */
    private function _isGreenTestResult()
    {
        return count($this->_errors) === 0 && 
               count($this->_failures) === 0 &&
               count($this->_incompletes) === 0 &&
               count($this->_skips) === 0;
    }

    /**
     * @return boolean
     */
    private function _isRedTestResult()
    {
        return count($this->_errors) > 0 ||
               count($this->_failures) > 0;
    }

    /**
     * @return boolean
     */
    private function _isYellowTestResult()
    {
        return count($this->_errors) === 0 &&
               count($this->_failures) === 0 &&
               (count($this->_incompletes) > 0 ||
                count($this->_skips) > 0);
    }
}