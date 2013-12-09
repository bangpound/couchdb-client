<?php

namespace Doctrine\CouchDB\View;

use Doctrine\CouchDB\HTTP\Client;
use Doctrine\CouchDB\HTTP\ErrorResponse;
use Doctrine\CouchDB\HTTP\HTTPException;

abstract class AbstractQuery
{
    /**
     * @var DesignDocument
     */
    protected $doc;

    /**
     * @var string
     */
    protected $designDocumentName;

    /**
     * @var string
     */
    protected $viewName;

    /**
     * @var string
     */
    protected $databaseName;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $params = array();

    /**
     * @param Client $client
     * @param string $designDocName
     * @param string $viewName
     * @param DesignDocument $doc
     */
    public function __construct(Client $client, $databaseName, $designDocName, $viewName, DesignDocument $doc = null)
    {
        $this->client = $client;
        $this->databaseName = $databaseName;
        $this->designDocumentName = $designDocName;
        $this->viewName = $viewName;
        $this->doc = $doc;
    }

    /**
     * Get all defined parameters.
     *
     * @return \Doctrine\Common\Collections\ArrayCollection The defined query parameters.
     */
    public function getParameters()
    {
        return $this->params;
    }

    /**
     * Gets a query parameter.
     *
     * @param mixed $key The key (index or name) of the parameter.
     *
     * @return mixed The value of the parameter.
     */
    public function getParameter($key)
    {
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }
        return null;
    }

    /**
     * Sets a collection of query parameters.
     *
     * @param array $params
     *
     * @return \Doctrine\CouchDB\View\AbstractQuery This query instance.
     */
    public function setParameters($params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Sets a query parameter.
     *
     * @param string|integer $key The parameter position or name.
     * @param mixed $value The parameter value.
     *
     * @return \Doctrine\ORM\AbstractQuery This query instance.
     */
    public function setParameter($key, $value)
    {
        $this->params[$key] = $value;
        return $this;
    }

    abstract protected function getHttpQuery();

    /**
     * Query the view with the current params.
     *
     * @return Result
     */
    public function execute()
    {
        return $this->createResult($this->doExecute());
    }

    protected function doExecute()
    {
        $path = $this->getHttpQuery();
        $method = "GET";
        $data = null;

        if ($this->getParameter("keys") !== null) {
            $method = "POST";
            $data = json_encode(array("keys" => $this->getParameter("keys")));
        }

        $response = $this->client->request($method, $path, $data);

        if ($response instanceof ErrorResponse && $this->doc) {
            $this->createDesignDocument();
            $response = $this->client->request($method, $path, $data);
        }

        if ($response->status >= 400) {
            throw HTTPException::fromResponse($path, $response);
        }
        return $response;
    }

    /**
     * @param $response
     * @return Result
     */
    abstract protected function createResult($response);

    /**
     * Create non existing view
     *
     * @return void
     * @throws \Doctrine\CouchDB\JsonDecodeException
     * @throws \Exception
     */
    public function createDesignDocument()
    {
        if (!$this->doc) {
            throw new \Exception("No DesignDocument Class is connected to this view query, cannot create the design document with its corresponding view automatically!");
        }

        $data = $this->doc->getData();
        if ($data === null) {
            throw \Doctrine\CouchDB\JsonDecodeException::fromLastJsonError();
        }
        $data['_id'] = '_design/' . $this->designDocumentName;

        $response = $this->client->request(
            "PUT",
            sprintf(
                "/%s/_design/%s",
                $this->databaseName,
                $this->designDocumentName
            ),
            json_encode($data)
        );
    }
}
