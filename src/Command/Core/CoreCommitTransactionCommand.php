<?php

/**
 * This file is part of the "-[:NEOXYGEN]->" NeoClient package
 *
 * (c) Neoxygen.io <http://neoxygen.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Neoxygen\NeoClient\Command\Core;

use Neoxygen\NeoClient\Command\AbstractCommand;

class CoreCommitTransactionCommand extends AbstractCommand
{
    const METHOD = 'POST';

    const PATH = '/db/data/transaction/';

    public $query;

    public $parameters;

    public $resultDataContents;

    public $transactionId;

    public function setArguments($transactionId, $query = null, array $parameters = array(), array $resultDataContents = array())
    {
        $this->transactionId = (int) $transactionId;
        $this->query = $query;
        $this->parameters = $parameters;
        $this->resultDataContents = $resultDataContents;

        return $this;
    }

    public function execute()
    {
        return $this->httpClient->send(self::METHOD, $this->getPath(), $this->prepareBody(), $this->connection);
    }

    public function prepareBody()
    {
        if (null === $this->query) {
            return null;
        }
        $statement = array();
        $statement['statement'] = $this->query;
        if (!empty($this->parameters)) {
            $statement['parameters'] = $this->parameters;
        }
        $body = array(
            'statements' => array(
                $statement
            )
        );

        return json_encode($body);
    }

    public function getPath()
    {
        return self::PATH . $this->getTransactionId() . '/commit';
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }
}
