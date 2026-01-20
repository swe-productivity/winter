<?php

namespace Backend\Tests\Widgets;

use System\Tests\Bootstrap\PluginTestCase;
use Backend\Tests\Fixtures\Models\UserFixture;
use Backend\Widgets\Filter;
use ApplicationException;
use Backend\Models\User;
use Carbon\Carbon;
use ReflectionMethod;

class FilterWidgetTest extends PluginTestCase
{
    public function testRestrictedScopeWithUserWithNoPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user);

        $filter = $this->restrictedFilterFixture();
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));

        // Expect an exception
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('No definition for scope email');
        $scope = $filter->getScope('email');
    }

    public function testRestrictedScopeWithUserWithWrongPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user->withPermission('test.wrong_permission', true));

        $filter = $this->restrictedFilterFixture();
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));

        // Expect an exception
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('No definition for scope email');
        $scope = $filter->getScope('email');
    }

    public function testRestrictedScopeWithUserWithRightPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user->withPermission('test.access_field', true));

        $filter = $this->restrictedFilterFixture();
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));
        $this->assertNotNull($filter->getScope('email'));
    }

    public function testRestrictedScopeWithUserWithRightWildcardPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user->withPermission('test.access_field', true));

        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'id' => [
                    'type' => 'text',
                    'label' => 'ID'
                ],
                'email' => [
                    'type' => 'text',
                    'label' => 'Email',
                    'permission' => 'test.*'
                ]
            ]
        ]);
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));
        $this->assertNotNull($filter->getScope('email'));
    }

    public function testRestrictedScopeWithSuperuser()
    {
        $user = new UserFixture;
        $this->actingAs($user->asSuperUser());

        $filter = $this->restrictedFilterFixture();
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));
        $this->assertNotNull($filter->getScope('email'));
    }

    public function testRestrictedScopeSinglePermissionWithUserWithWrongPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user->withPermission('test.wrong_permission', true));

        $filter = $this->restrictedFilterFixture(true);
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));

        // Expect an exception
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('No definition for scope email');
        $scope = $filter->getScope('email');
    }

    public function testRestrictedScopeSinglePermissionWithUserWithRightPermissions()
    {
        $user = new UserFixture;
        $this->actingAs($user->withPermission('test.access_field', true));

        $filter = $this->restrictedFilterFixture(true);
        $filter->render();

        $this->assertNotNull($filter->getScope('id'));
        $this->assertNotNull($filter->getScope('email'));
    }

    protected function restrictedFilterFixture(bool $singlePermission = false)
    {
        return new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'id' => [
                    'type' => 'text',
                    'label' => 'ID'
                ],
                'email' => [
                    'type' => 'text',
                    'label' => 'Email',
                    'permissions' => ($singlePermission) ? 'test.access_field' : [
                        'test.access_field'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test that when only start date is provided, end date defaults to 2037-12-31 23:59:59
     * This ensures the date is within MySQL TIMESTAMP limits (max: 2038-01-19 03:14:07)
     */
    public function testDateRangeFilterWithOnlyStartDate()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        // Simulate AJAX data with only start date (end date is empty)
        $ajaxDates = ['2026-01-01 00:00:00', ''];
        $result = $reflection->invoke($filter, $ajaxDates);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Carbon::class, $result[0]);
        $this->assertInstanceOf(Carbon::class, $result[1]);
        
        // Start date should be as provided
        $this->assertEquals('2026-01-01 00:00:00', $result[0]->format('Y-m-d H:i:s'));
        
        // End date should default to 2037-12-31 23:59:59 (not 2999-12-31)
        $this->assertEquals('2037-12-31 23:59:59', $result[1]->format('Y-m-d H:i:s'));
        
        // Verify it's within MySQL TIMESTAMP limits
        $mysqlMaxTimestamp = Carbon::createFromTimestamp(2147483647); // 2038-01-19 03:14:07
        $this->assertTrue($result[1]->lt($mysqlMaxTimestamp), 'End date must be less than MySQL TIMESTAMP maximum');
    }

    /**
     * Test that when only end date is provided, start date defaults to 0000-01-01 00:00:00
     */
    public function testDateRangeFilterWithOnlyEndDate()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        // Simulate AJAX data with only end date (start date is empty)
        $ajaxDates = ['', '2026-12-31 23:59:59'];
        $result = $reflection->invoke($filter, $ajaxDates);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Carbon::class, $result[0]);
        $this->assertInstanceOf(Carbon::class, $result[1]);
        
        // Start date should default to 0000-01-01 00:00:00
        $this->assertEquals('0000-01-01 00:00:00', $result[0]->format('Y-m-d H:i:s'));
        
        // End date should be as provided
        $this->assertEquals('2026-12-31 23:59:59', $result[1]->format('Y-m-d H:i:s'));
    }

    /**
     * Test that when both dates are provided, they are used as-is
     */
    public function testDateRangeFilterWithBothDates()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        // Simulate AJAX data with both dates provided
        $ajaxDates = ['2026-01-01 00:00:00', '2026-12-31 23:59:59'];
        $result = $reflection->invoke($filter, $ajaxDates);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Carbon::class, $result[0]);
        $this->assertInstanceOf(Carbon::class, $result[1]);
        
        $this->assertEquals('2026-01-01 00:00:00', $result[0]->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-12-31 23:59:59', $result[1]->format('Y-m-d H:i:s'));
    }

    /**
     * Test that the default end date (2037-12-31 23:59:59) is within MySQL TIMESTAMP limits
     */
    public function testDefaultEndDateWithinMySQLTimestampLimits()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        // Test with empty end date
        $ajaxDates = ['2026-01-01 00:00:00', ''];
        $result = $reflection->invoke($filter, $ajaxDates);

        $defaultEndDate = $result[1];
        
        // MySQL TIMESTAMP maximum: 2038-01-19 03:14:07 (Unix timestamp: 2147483647)
        $mysqlMaxTimestamp = Carbon::createFromTimestamp(2147483647);
        
        // Verify default end date is less than MySQL maximum
        $this->assertTrue(
            $defaultEndDate->lt($mysqlMaxTimestamp),
            'Default end date (2037-12-31 23:59:59) must be less than MySQL TIMESTAMP maximum (2038-01-19 03:14:07)'
        );
        
        // Verify it's exactly what we expect
        $this->assertEquals('2037-12-31 23:59:59', $defaultEndDate->format('Y-m-d H:i:s'));
    }

    /**
     * Test that null input returns empty array
     */
    public function testDateRangeFilterWithNullInput()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($filter, null);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that invalid date format returns empty array
     */
    public function testDateRangeFilterWithInvalidDateFormat()
    {
        $filter = new Filter(null, [
            'model' => new User,
            'arrayName' => 'array',
            'scopes' => [
                'date_range' => [
                    'type' => 'daterange',
                    'label' => 'Date Range'
                ]
            ]
        ]);

        $reflection = new ReflectionMethod($filter, 'datesFromAjax');
        $reflection->setAccessible(true);

        // Invalid date format
        $ajaxDates = ['invalid-date', '2026-12-31'];
        $result = $reflection->invoke($filter, $ajaxDates);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
