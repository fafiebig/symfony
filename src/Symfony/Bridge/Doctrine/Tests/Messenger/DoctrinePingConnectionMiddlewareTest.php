<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bridge\Doctrine\Messenger\DoctrinePingConnectionMiddleware;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

class DoctrinePingConnectionMiddlewareTest extends MiddlewareTestCase
{
    private MockObject&Connection $connection;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&ManagerRegistry $managerRegistry;
    private DoctrinePingConnectionMiddleware $middleware;
    private string $entityManagerName = 'default';

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry->method('getManager')->willReturn($this->entityManager);

        $this->middleware = new DoctrinePingConnectionMiddleware(
            $this->managerRegistry,
            $this->entityManagerName
        );
    }

    public function testMiddlewarePingOk()
    {
        $this->connection->expects($this->once())
            ->method('getDatabasePlatform')
            ->will($this->throwException(new DBALException()));

        $this->connection->expects($this->once())
            ->method('close')
        ;
        $this->connection->expects($this->once())
            ->method('connect')
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $this->middleware->handle($envelope, $this->getStackMock());
    }

    public function testMiddlewarePingResetEntityManager()
    {
        $this->connection->expects($this->once())
            ->method('getDatabasePlatform')
            ->will($this->throwException(new DBALException()));

        $this->entityManager->expects($this->once())
            ->method('isOpen')
            ->willReturn(false)
        ;
        $this->managerRegistry->expects($this->once())
            ->method('resetManager')
            ->with($this->entityManagerName)
        ;

        $envelope = new Envelope(new \stdClass(), [
            new ConsumedByWorkerStamp(),
        ]);
        $this->middleware->handle($envelope, $this->getStackMock());
    }

    public function testInvalidEntityManagerThrowsException()
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManager')
            ->with('unknown_manager')
            ->will($this->throwException(new \InvalidArgumentException()));

        $middleware = new DoctrinePingConnectionMiddleware($managerRegistry, 'unknown_manager');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock(false));
    }

    public function testMiddlewareNoPingInNonWorkerContext()
    {
        $this->connection->expects($this->never())
            ->method('close')
        ;
        $this->connection->expects($this->never())
            ->method('connect')
        ;

        $envelope = new Envelope(new \stdClass());
        $this->middleware->handle($envelope, $this->getStackMock());
    }
}
