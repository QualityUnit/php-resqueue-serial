<?php


namespace Test;

use Resque\Key;
use Resque\Protocol\DeferredException;
use Resque\Protocol\DiscardedException;
use Resque\Protocol\UniqueLock;
use Resque\Protocol\UniqueLockMissingException;
use Resque\Protocol\UniqueState;
use Resque\Resque;

class UniqueLockTest extends RedisTestCase {

    const PLD_BUFFER = 'buffered_payload';
    const Q_BUFFER = 'buffer';
    const Q_DESTINATION = 'destination';
    const U_LOCKED = 'urunning';
    const U_LOCKED_OLD = 'urunningold';

    public function falsyValueProvider() {
        return [[0], [false], [''], [null]];
    }

    public function testClearLock() {
        $this->setDeferredPayload(self::U_LOCKED, 'payload');

        UniqueLock::clearLock(self::U_LOCKED);

        self::assertFalse($this->getRawState(self::U_LOCKED));
        self::assertFalse($this->getDeferredPayload(self::U_LOCKED));
        self::assertNotEmpty($this->getRawState(self::U_LOCKED_OLD));
    }

    public function testLock_AlreadyLockedDeferrableJobIsDeferred() {
        $expectedRawState = $this->getRawState(self::U_LOCKED);

        try {
            UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, true);
            self::fail('Exception should have been thrown.');
        } catch (DeferredException $ignore) {
        }

        $this->assertFirstListValue(self::Q_BUFFER, false);
        self::assertEquals(self::PLD_BUFFER, $this->getDeferredPayload(self::U_LOCKED));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_LOCKED));
        $this->assertState(self::U_LOCKED, UniqueLock::STATE_RUNNING);
    }

    public function testLock_AlreadyLockedNonDeferrableJobIsDiscarded_WithExistingDeferral() {
        $expectedRawState = $this->getRawState(self::U_LOCKED);
        $this->setDeferredPayload(self::U_LOCKED, 'existing_payload');

        try {
            UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, false);
            self::fail('Exception should have been thrown.');
        } catch (DiscardedException $ignore) {
        }

        $this->assertFirstListValue(self::Q_BUFFER, false);
        self::assertEquals('existing_payload', $this->getDeferredPayload(self::U_LOCKED));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_LOCKED));
    }

    public function testLock_AlreadyLockedNonDeferrableJobIsDiscarded_WithoutExistingDeferral() {
        $expectedRawState = $this->getRawState(self::U_LOCKED);

        try {
            UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, false);
            self::fail('Exception should have been thrown.');
        } catch (DiscardedException $ignore) {
        }

        $this->assertFirstListValue(self::Q_BUFFER, false);
        self::assertFalse($this->getDeferredPayload(self::U_LOCKED));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_LOCKED));
    }

    public function testLock_DeferralDoesNotOverrideAlreadyDeferredJob() {
        $this->setDeferredPayload(self::U_LOCKED, 'existing_payload');
        $expectedRawState = $this->getRawState(self::U_LOCKED);

        try {
            UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, true);
            self::fail('Exception should have been thrown.');
        } catch (DeferredException $ignore) {
        }

        $this->assertFirstListValue(self::Q_BUFFER, false);
        self::assertEquals('existing_payload', $this->getDeferredPayload(self::U_LOCKED));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_LOCKED));
        $this->assertState(self::U_LOCKED, UniqueLock::STATE_RUNNING);
    }

    public function testLock_ExceptionIsThrownOnDeferral() {
        $this->expectException(DeferredException::class);

        UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, true);
    }

    public function testLock_ExceptionIsThrownOnDiscard() {
        $this->expectException(DiscardedException::class);

        UniqueLock::lock(self::U_LOCKED, self::Q_BUFFER, false);
    }

    public function testLock_NewDeferrableJobIsLocked() {
        UniqueLock::lock('fresh', self::Q_BUFFER, true);

        $this->assertFirstListValue(self::Q_BUFFER, self::PLD_BUFFER);
        $this->assertState('fresh', UniqueLock::STATE_RUNNING);
        $this->assertState(self::U_LOCKED, UniqueLock::STATE_RUNNING);
    }

    public function testLock_NewNonDeferrableJobIsLocked() {
        UniqueLock::lock('fresh', self::Q_BUFFER, false);

        $this->assertFirstListValue(self::Q_BUFFER, self::PLD_BUFFER);
        $this->assertState('fresh', UniqueLock::STATE_RUNNING);
        $this->assertState(self::U_LOCKED, UniqueLock::STATE_RUNNING);
    }

    public function testLock_OldRunningJobIsClearedBeforeLocking_Deferrable() {
        $oldRawState = $this->getRawState(self::U_LOCKED_OLD);

        UniqueLock::lock(self::U_LOCKED_OLD, self::Q_BUFFER, true);

        $this->assertFirstListValue(self::Q_BUFFER, self::PLD_BUFFER);
        self::assertFalse($this->getDeferredPayload(self::U_LOCKED_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_LOCKED_OLD));
        $this->assertState(self::U_LOCKED_OLD, UniqueLock::STATE_RUNNING);
    }

    public function testLock_OldRunningJobIsClearedBeforeLocking_DeferrableWithExistingDeferred() {
        $oldRawState = $this->getRawState(self::U_LOCKED_OLD);
        $this->setDeferredPayload(self::U_LOCKED_OLD, 'existing_payload');

        UniqueLock::lock(self::U_LOCKED_OLD, self::Q_BUFFER, true);

        $this->assertFirstListValue(self::Q_BUFFER, self::PLD_BUFFER);
        self::assertFalse($this->getDeferredPayload(self::U_LOCKED_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_LOCKED_OLD));
        $this->assertState(self::U_LOCKED_OLD, UniqueLock::STATE_RUNNING);
    }

    public function testLock_OldRunningJobIsClearedBeforeLocking_NonDeferrable() {
        $oldRawState = $this->getRawState(self::U_LOCKED_OLD);

        UniqueLock::lock(self::U_LOCKED_OLD, self::Q_BUFFER, false);

        $this->assertFirstListValue(self::Q_BUFFER, self::PLD_BUFFER);
        self::assertFalse($this->getDeferredPayload(self::U_LOCKED_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_LOCKED_OLD));
        $this->assertState(self::U_LOCKED_OLD, UniqueLock::STATE_RUNNING);
    }

    public function testUnlock_DoesNotAlterAnyStateOnMissingLock() {
        try {
            UniqueLock::unlock('not-exists');
            self::fail('Exception should have been thrown.');
        } catch (UniqueLockMissingException $ignore) {
        }

        $this->assertKeyExists(Key::uniqueState('not-exists'), false);
        $this->assertKeyExists(Key::uniqueDeferred('not-exists'), false);
    }

    /**
     * @dataProvider falsyValueProvider
     *
     * @param mixed $falsyValue
     *
     * @throws \Resque\RedisError
     * @throws \Resque\Protocol\UniqueLockMissingException
     */
    public function testUnlock_FailsForFalsyValue($falsyValue) {
        $this->expectException(\InvalidArgumentException::class);

        UniqueLock::unlock($falsyValue);
    }

    public function testUnlock_LockIsReleased() {
        $result = UniqueLock::unlock(self::U_LOCKED);

        self::assertEquals(false, $result);
        $this->assertKeyExists(Key::uniqueState(self::U_LOCKED), false);
        $this->assertKeyExists(Key::uniqueDeferred(self::U_LOCKED), false);
    }

    public function testUnlock_LockIsReleasedAndDeferredJobIsReturned() {
        $this->setDeferredPayload(self::U_LOCKED, 'deferred_payload');

        $result = UniqueLock::unlock(self::U_LOCKED);

        self::assertEquals('deferred_payload', $result);
        $this->assertKeyExists(Key::uniqueState(self::U_LOCKED), false);
        $this->assertKeyExists(Key::uniqueDeferred(self::U_LOCKED), false);
    }

    public function testUnlock_ThrowsExceptionOnMissingLock() {
        $this->expectException(UniqueLockMissingException::class);

        UniqueLock::unlock('not-exists');
    }

    protected function setUp() {
        parent::setUp();
        $this->addKeys([
            Key::uniqueState(self::U_LOCKED) => $this->state(UniqueLock::STATE_RUNNING, time()),
            Key::uniqueState(self::U_LOCKED_OLD) => $this->state(UniqueLock::STATE_RUNNING, time() - 4000),
        ]);
        Resque::redis()->lPush(self::Q_BUFFER, self::PLD_BUFFER);
    }

    private function assertState($uniqueId, $state) {
        $actualState = UniqueState::fromString($this->getRawState($uniqueId))->stateName;

        self::assertEquals($state, $actualState, "'$uniqueId' state mismatch. Expected: $state, Actual: $actualState");
    }

    private function getDeferredPayload($uniqueId) {
        return Resque::redis()->get(Key::uniqueDeferred($uniqueId));
    }

    private function getRawState($uniqueId) {
        return Resque::redis()->get(Key::uniqueState($uniqueId));
    }

    private function setDeferredPayload($uniqueId, $payload) {
        Resque::redis()->set(Key::uniqueDeferred($uniqueId), $payload);
    }

    private function state($stateName, $time) {
        return (new UniqueState($stateName, $time))->toString();
    }
}
