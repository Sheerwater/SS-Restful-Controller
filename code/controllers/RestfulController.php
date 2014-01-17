<?php

namespace Sheerwater\RestfulController;

use Sheerwater\RestfulController\Interfaces\RestfulAuthenticatorInterface;
use Controller, Convert, DataFormatter, DataObjectInterface, Member, SS_HTTPRequest, SS_List;
use Exception, stdClass;

class RestfulController extends \Controller
{
    private static $allowed_actions = [
        'handleActionOrId'
    ];
    private static $url_handlers = [
        '//$ID'                                                                                     => 'index',
        '$Action//$param1/$param2/$param3/$param4/$param5/$param6/$param7/$param8/$param9/$param10' => 'handleRestfulAction'
    ];

    public function index(SS_HTTPRequest $request)
    {
        $out = null;

        $id = $request->param('ID');

        if ($request->isGET() and method_exists($this, 'get')) {
            $out = $this->get($id);
        } elseif ($request->isDELETE() and method_exists($this, 'delete')) {
            $out = $this->delete($id);
        } elseif ($request->isPOST() and method_exists($this, 'post')) {
            $out = $this->post($request->getBody());
        } elseif ($request->isPUT() and method_exists($this, 'put')) {
            $out = $this->put($id, $request->getBody());
        } else {
            $this->httpError(403, 'Unsupported HTTP method.');

            return null;
        }

        return $this->formatOutput($out);
    }

    public function handleRestfulAction(SS_HTTPRequest $request)
    {
        $out = null;

        $action = $request->param('Action');

        if (method_exists($this, $action) and in_array($this->allowedActions(get_class($this)), $action)) {
            $params      = $request->latestParams();
            $paramsToUse = [];
            foreach ($params as $k => $v) {
                if (substr($k, 0, 5) == 'param' and is_numeric(substr(5, 1))) {
                    $paramsToUse[] = $v;
                }
            }

            $out = call_user_func_array([$this, $action], $paramsToUse);
        }

        return $this->formatOutput($out);
    }

    private function formatOutput($output)
    {
        $formatter = static::getResponseDataFormatter($this->request);

        if ($output instanceof DataObjectInterface) {
            return $formatter->convertDataObject($output);
        } elseif ($output instanceof SS_List) {
            $formatter->setTotalSize($output->dataQuery()->query()->unlimitedRowCount());

            return $formatter->convertDataObjectSet($output);
        } else {
            if (is_array($output) or $output instanceof stdClass) {
                // Try to do a simple conversion to json or bson
                $outputType = $formatter->getOutputContentType();
                if (substr($outputType, -5) === '/json') {
                    return Convert::raw2json($output);
                } elseif (substr($outputType, -5) === '/bson' and function_exists('bson_encode')) {
                    return bson_encode($output);
                }
            }
        }

        return $output;
    }

    private static $default_extension = 'json';

    /**
     * Borrowed from RestfulServer
     * Returns a DataFormatter instance based on the request extension or mimetype. Falls back to
     * {@link self::$default_extension}.
     *
     * @param SS_HTTPRequest $request
     * @param boolean        $includeAcceptHeader Determines whether to inspect and prioritize any HTTP Accept headers
     *
     * @return DataFormatter
     */
    protected static function getDataFormatter(SS_HTTPRequest $request, $includeAcceptHeader = false)
    {
        $extension               = $request->getExtension();
        $contentTypeWithEncoding = $request->getHeader('Content-Type');
        preg_match('/([^;]*)/', $contentTypeWithEncoding, $contentTypeMatches);
        $contentType = $contentTypeMatches[0];
        $accept      = $request->getHeader('Accept');
        $mimetypes   = $request->getAcceptMimetypes();

        // get formatter
        if (!empty($extension)) {
            $formatter = DataFormatter::for_extension($extension);
        } elseif ($includeAcceptHeader && !empty($accept) && $accept != '*/*') {
            $formatter = DataFormatter::for_mimetypes($mimetypes);
            if (!$formatter) $formatter = DataFormatter::for_extension(self::$default_extension);
        } elseif (!empty($contentType)) {
            $formatter = DataFormatter::for_mimetype($contentType);
        } else {
            $formatter = DataFormatter::for_extension(self::config()->get('default_extension'));
        }

        if (!$formatter) return false;

        return $formatter;
    }

    /**
     * Borrowed from RestfulServer
     *
     * @param SS_HTTPRequest $request
     *
     * @return DataFormatter
     */
    protected static function getRequestDataFormatter(SS_HTTPRequest $request)
    {
        return static::getDataFormatter($request, false);
    }

    /**
     * Borrowed from RestfulServer
     *
     * @param SS_HTTPRequest $request
     *
     * @return DataFormatter
     */
    protected function getResponseDataFormatter(SS_HTTPRequest $request)
    {
        return static::getDataFormatter($request, true);
    }

    private $onBeforeAuthenticateCalled = false;

    protected function onBeforeAuthenticate()
    {
        $this->extend('onBeforeAuthenticate');
        $this->onBeforeAuthenticateCalled = true;
    }

    private $onAfterAuthenticateCalled = false;

    protected function onAfterAuthenticate(Member $member)
    {
        $this->extend('onAfterAuthenticate', $member);
        $this->onAfterAuthenticateCalled = true;
    }

    private static $authenticator = null;

    /**
     * A function to authenticate a user. You can hook into onBeforeAuthenticate and onAfterAuthenticate in a
     * subclass or extension to do things such as read or manipulate headers or perform other required
     * supporting actions.
     *
     * @throws Exception
     * @return Member|false The logged in member
     */
    protected function authenticate()
    {
        $this->onBeforeAuthenticate();
        if (!$this->onBeforeAuthenticateCalled) {
            throw new Exception(
                'When overriding RestfulController#onBeforeAuthenticate you must call parent::onBeforeAuthenticate.');
        }

        $member    = null;
        $authClass = self::config()->get('authenticator');
        if (singleton($authClass) instanceof RestfulAuthenticatorInterface) {
            $member = $authClass::authenticate();
        }

        $this->onAfterAuthenticate($member);
        if (!$this->onAfterAuthenticateCalled) {
            throw new Exception(
                'When overriding RestfulController#onAfterAuthenticate you must call parent::onAfterAuthenticate.');
        }

        return $member;
    }

    public function Link($action = null)
    {
        $link = parent::Link();
        if (!is_null($action) and (method_exists($this, $action) and in_array($this->allowedActions(true), $action))) {
            return Controller::join_links($link, $action);
        } else {
            return $link;
        }
    }
} 
