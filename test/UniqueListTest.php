<?php


namespace Test;

use Resque\Key;
use Resque\Protocol\UniqueList;
use Resque\Protocol\UniqueState;
use Resque\Resque;

class UniqueListTest extends RedisTestCase {

    const PLD_BUFFER = 'buffered_payload';
    const Q_BUFFER = 'buffer';
    const Q_DESTINATION = 'destination';
    const U_QUEUED = 'uqueued';
    const U_QUEUED_OLD = 'uqueuedold';
    const U_RUNNING = 'urunning';
    const U_RUNNING_OLD = 'urunningold';

    public function falsyValueProvider() {
        return [[0], [false], [''], [null]];
    }

    public function testAddNewDeferable() {
        $result = UniqueList::add('fresh', self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertEquals(self::PLD_BUFFER, $result);
        $this->assertState('fresh', UniqueList::STATE_QUEUED);
        $this->assertState(self::U_QUEUED, UniqueList::STATE_QUEUED);
        $this->assertFirstListValue(self::Q_DESTINATION, self::PLD_BUFFER);
    }

    public function testAddNewNonDeferrable() {
        $result = UniqueList::add('fresh', self::Q_BUFFER, self::Q_DESTINATION, false);

        self::assertEquals(self::PLD_BUFFER, $result);
        $this->assertState('fresh', UniqueList::STATE_QUEUED);
        $this->assertState(self::U_QUEUED, UniqueList::STATE_QUEUED);
        $this->assertFirstListValue(self::Q_DESTINATION, self::PLD_BUFFER);
    }

    public function testAddQueuedDeferrable() {
        $expectedRawState = $this->getRawState(self::U_QUEUED);

        $result = UniqueList::add(self::U_QUEUED, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertFalse($result);
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_QUEUED));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddQueuedNonDeferrable() {
        $expectedRawState = $this->getRawState(self::U_QUEUED);

        $result = UniqueList::add(self::U_QUEUED, self::Q_BUFFER, self::Q_DESTINATION, false);

        self::assertFalse($result);
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_QUEUED));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddQueuedOldDeferrable() {
        $expectedRawState = $this->getRawState(self::U_QUEUED_OLD);

        $result = UniqueList::add(self::U_QUEUED_OLD, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertFalse($result);
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_QUEUED_OLD));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddQueuedOldNonDeferrable() {
        $expectedRawState = $this->getRawState(self::U_QUEUED_OLD);

        $result = UniqueList::add(self::U_QUEUED_OLD, self::Q_BUFFER, self::Q_DESTINATION, false);

        self::assertFalse($result);
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_QUEUED_OLD));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddRunningDeferrableExisting() {
        $this->setDeferredPayload(self::U_RUNNING, 'existing_payload');
        $expectedRawState = $this->getRawState(self::U_RUNNING);

        $result = UniqueList::add(self::U_RUNNING, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertEquals(self::PLD_BUFFER, $result);
        self::assertEquals('existing_payload', $this->getDeferredPayload(self::U_RUNNING));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_RUNNING));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddRunningDeferrableNew() {
        $expectedRawState = $this->getRawState(self::U_RUNNING);

        $result = UniqueList::add(self::U_RUNNING, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertEquals(self::PLD_BUFFER, $result);
        self::assertEquals(self::PLD_BUFFER, $this->getDeferredPayload(self::U_RUNNING));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_RUNNING));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddRunningNonDeferrable() {
        $expectedRawState = $this->getRawState(self::U_RUNNING);

        $result = UniqueList::add(self::U_RUNNING, self::Q_BUFFER, self::Q_DESTINATION, false);

        self::assertFalse($result);
        self::assertFalse($this->getDeferredPayload(self::U_RUNNING));
        $this->assertEquals($expectedRawState, $this->getRawState(self::U_RUNNING));
        $this->assertState(self::U_RUNNING, UniqueList::STATE_RUNNING);
        $this->assertFirstListValue(self::Q_DESTINATION, false);
    }

    public function testAddRunningOldDeferrableExisting() {
        $oldRawState = $this->getRawState(self::U_RUNNING_OLD);
        $this->setDeferredPayload(self::U_RUNNING_OLD, 'existing_payload');

        $result = UniqueList::add(self::U_RUNNING_OLD, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertEquals(self::PLD_BUFFER, $result);
        self::assertFalse($this->getDeferredPayload(self::U_RUNNING_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_RUNNING_OLD));
        $this->assertState(self::U_RUNNING_OLD, UniqueList::STATE_QUEUED);
        $this->assertFirstListValue(self::Q_DESTINATION, self::PLD_BUFFER);
    }

    public function testAddRunningOldDeferrableNew() {
        $oldRawState = $this->getRawState(self::U_RUNNING_OLD);

        $result = UniqueList::add(self::U_RUNNING_OLD, self::Q_BUFFER, self::Q_DESTINATION, true);

        self::assertEquals(self::PLD_BUFFER, $result);
        self::assertFalse($this->getDeferredPayload(self::U_RUNNING_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_RUNNING_OLD));
        $this->assertState(self::U_RUNNING_OLD, UniqueList::STATE_QUEUED);
        $this->assertFirstListValue(self::Q_DESTINATION, self::PLD_BUFFER);
    }

    public function testAddRunningOldNonDeferrable() {
        $oldRawState = $this->getRawState(self::U_RUNNING_OLD);

        $result = UniqueList::add(self::U_RUNNING_OLD, self::Q_BUFFER, self::Q_DESTINATION, false);

        self::assertEquals(self::PLD_BUFFER, $result);
        self::assertFalse($this->getDeferredPayload(self::U_RUNNING_OLD));
        $this->assertNotEquals($oldRawState, $this->getRawState(self::U_RUNNING_OLD));
        $this->assertState(self::U_RUNNING_OLD, UniqueList::STATE_QUEUED);
        $this->assertFirstListValue(self::Q_DESTINATION, self::PLD_BUFFER);
    }

    public function testFinalizeDeferred() {
        $this->setDeferredPayload(self::U_RUNNING, 'deferred_payload');

        $result = UniqueList::finalize(self::U_RUNNING);

        self::assertEquals('deferred_payload', $result);
        $this->assertKeyExists(Key::uniqueState(self::U_RUNNING), false);
        $this->assertKeyExists(Key::uniqueDeferred(self::U_RUNNING), false);
    }

    /**
     * @dataProvider falsyValueProvider
     *
     * @param mixed $falsyValue
     *
     * @throws \Resque\RedisError
     */
    public function testFinalizeFalsy($falsyValue) {
        $this->expectException(\InvalidArgumentException::class);

        UniqueList::finalize($falsyValue);
    }

    public function testFinalizeNoDeferred() {
        $result = UniqueList::finalize(self::U_RUNNING);

        self::assertEquals(false, $result);
        $this->assertKeyExists(Key::uniqueState(self::U_RUNNING), false);
        $this->assertKeyExists(Key::uniqueDeferred(self::U_RUNNING), false);
    }

    public function testFinalizeNotExists() {
        $result = UniqueList::finalize('not-exists');

        self::assertEquals(1, $result);
        $this->assertKeyExists(Key::uniqueState('not-exists'), false);
        $this->assertKeyExists(Key::uniqueDeferred('not-exists'), false);
    }

    public function testFinalizeNotRunning() {
        $result = UniqueList::finalize(self::U_QUEUED);

        self::assertEquals(2, $result);
        $this->assertKeyExists(Key::uniqueState(self::U_QUEUED));
    }

    public function testRemoveAll() {
        $this->setDeferredPayload(self::U_RUNNING, 'payload');

        UniqueList::removeAll(self::U_RUNNING);

        self::assertFalse($this->getRawState(self::U_RUNNING));
        self::assertFalse($this->getDeferredPayload(self::U_RUNNING));
        self::assertNotEmpty($this->getRawState(self::U_RUNNING_OLD));
    }

    /**
     * @dataProvider falsyValueProvider
     *
     * @param mixed $falsyValue
     *
     * @throws \Resque\RedisError
     */
    public function testSetRunningFalsy($falsyValue) {
        $this->expectException(\InvalidArgumentException::class);

        UniqueList::setRunning($falsyValue);
    }

    public function testSetRunningFromQueued() {
        $result = UniqueList::setRunning(self::U_QUEUED);

        self::assertTrue($result);
        $this->assertState(self::U_QUEUED, UniqueList::STATE_RUNNING);
    }

    public function testSetRunningFromRunning() {
        $oldRunningState = $this->getRawState(self::U_RUNNING_OLD);
        $result = UniqueList::setRunning(self::U_RUNNING_OLD);

        self::assertTrue($result);
        $this->assertState(self::U_RUNNING_OLD, UniqueList::STATE_RUNNING);
        self::assertNotEquals($oldRunningState, $this->getRawState(self::U_RUNNING_OLD));
    }

    public function testSetRunningNotExists() {
        $result = UniqueList::setRunning('fake');

        self::assertFalse($result);
        $this->assertKeyExists(Key::uniqueState('fake'), false);
    }

    protected function setUp() {
        parent::setUp();
        $this->addKeys([
            Key::uniqueState(self::U_QUEUED) => $this->state(UniqueList::STATE_QUEUED, time()),
            Key::uniqueState(self::U_RUNNING) => $this->state(UniqueList::STATE_RUNNING, time()),
            Key::uniqueState(self::U_QUEUED_OLD) => $this->state(UniqueList::STATE_QUEUED, time() - 4000),
            Key::uniqueState(self::U_RUNNING_OLD) => $this->state(UniqueList::STATE_RUNNING, time() - 4000),
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
