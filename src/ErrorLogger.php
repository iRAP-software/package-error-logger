<?php

/* 
 * This class is to make it easy to hook up logging of all errors, including fatal ones by 
 * registering an error handler and shutdown handler (for the fatal errors).
 * All you have to do is create this object. You don't have do anything with it.
 */

namespace iRAP\ErrorLogger;

class ErrorLogger
{
    /**
     * Create an error logger which will be sure to log all errors to the provided logger.
     * @param iRAP\Logging\LoggerInterface $logger - the logger we will log errors to.
     * @param type $serviceName - the name of this service to appear in logs. You may want to use
     *                            the hostname, and maybe the environment (dev, live/production etc)
     * @param array $extraContext - any additional name/value pairs you may wish to have logged.
     * @param type $otherErrorHandlers - any other errorHandlers you also want to be triggered when
     *                                   there is an error.
     */
    public function __construct(\iRAP\Logging\LoggerInterface $logger, $serviceName, array $extraContext=array(), $otherErrorHandlers = array())
    {
        /**
         * Convert a log error code/type to the appropriate loglevel.
         * @return int - the appropriate log level.
         */
        $logLevelConterter = function($logError) {
            $logLevel = \iRAP\Logging\LogLevel::ERROR;

            if 
            (
                   $logError == E_NOTICE
                || $logError == E_USER_NOTICE
                || $logError == E_STRICT
                || $logError == E_DEPRECATED
                || $logError == E_USER_DEPRECATED
            )
            {
                $logLevel = \iRAP\Logging\LogLevel::NOTICE;
            }

            if 
            (
                   $logError == E_WARNING
                || $logError == E_CORE_WARNING
                || $logError == E_COMPILE_WARNING
                || $logError == E_USER_WARNING
            )
            {
                $logLevel = \iRAP\Logging\LogLevel::WARNING;
            }

            return $logLevel;
        };
        
        # Register an error handler to alert the admins if anything goes wrong.
        $errorHandler = function($errorLevel, $errstr, $errfile, $errline) use ($logger, $extraContext, $logLevelConterter, $serviceName, $otherErrorHandlers) {
            
            # grab a string representation of the backtrace
            $backtraceString = json_encode(debug_backtrace(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $context = array(
                'error_string'  => $errstr,
                'service'       => $serviceName,
                'error_level'   => $logLevelConterter($errorLevel),
                'file'          => $errfile,
                'line'          => $errline,
                'backtrace'     => $backtraceString,
            );
            
            $allContext = array_merge($context, $extraContext);
            $message = "There was an issue with: " . $serviceName . ". " . $errstr;
            $logLevel = $logLevelConterter($errorLevel);
            $logger->log($logLevel, $message, $allContext);
            
            foreach ($otherErrorHandlers as $callback)
            {
                $callback($errorLevel, $errstr, $errfile, $errline);
            }
        };

        # Register a shutdown handler to alert the admins if anything goes wrong.
        $shutdownHandler = function() use ($logger, $extraContext, $logLevelConterter, $serviceName) {
            $error = error_get_last();

            if (!empty($error))
            {
                $logLevel = $logLevelConterter($error['type']);
                
                $context = array(
                    'message' => $error['message'],
                    'service' => $serviceName,
                    'file'    => $error['file'],
                    'line'    => $error['line'],
                    'service' => $serviceName
                );
                
                $allContext = array_merge($context, $extraContext);

                $message = "There was a fatal error with the " . $serviceName . ". " . $error['message'];
                $logger->log($logLevel, $message, $context);
            }
        };

        set_error_handler($errorHandler);
        register_shutdown_function($shutdownHandler);
    }
}

