<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\Wishlist;

use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\Wishlist\Model\Item;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory;

class CustomerWishlistTest extends GraphQlAbstract
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @var CollectionFactory
     */
    private $wishlistCollectionFactory;

    protected function setUp(): void
    {
        $this->customerTokenService = Bootstrap::getObjectManager()->get(CustomerTokenServiceInterface::class);
        $this->wishlistCollectionFactory = Bootstrap::getObjectManager()->get(CollectionFactory::class);
    }

    /**
     * @magentoConfigFixture default_store wishlist/general/active 1
     * @magentoApiDataFixture Magento/Wishlist/_files/wishlist.php
     */
    public function testCustomerWishlist(): void
    {
        /** @var \Magento\Wishlist\Model\Wishlist $wishlist */
        $collection = $this->wishlistCollectionFactory->create()->filterByCustomerId(1);

        /** @var Item $wishlistItem */
        $wishlistItem = $collection->getFirstItem();
        $query =
            <<<QUERY
{
  customer {
    wishlist {
      id
      items_count
      sharing_code
      updated_at
      items {
        product {
          sku
        }
      }
    }
  }
}
QUERY;

        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders('customer@example.com', 'password')
        );
        $this->assertEquals((string)$wishlistItem->getId(), $response['customer']['wishlist']['id']);
        $this->assertEquals($wishlistItem->getItemsCount(), $response['customer']['wishlist']['items_count']);
        $this->assertEquals($wishlistItem->getSharingCode(), $response['customer']['wishlist']['sharing_code']);
        $this->assertEquals($wishlistItem->getUpdatedAt(), $response['customer']['wishlist']['updated_at']);
        $this->assertEquals('simple', $response['customer']['wishlist']['items'][0]['product']['sku']);
    }

    /**
     * @magentoConfigFixture default_store wishlist/general/active 1
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testCustomerAlwaysHasWishlist(): void
    {
        $query =
            <<<QUERY
{
  customer {
    wishlist {
      id
    }
  }
}
QUERY;

        $response = $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders('customer@example.com', 'password')
        );

        $this->assertNotEmpty($response['customer']['wishlist']['id']);
    }

    /**
     * @magentoConfigFixture default_store wishlist/general/active 1
     */
    public function testGuestCannotGetWishlist()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current customer isn\'t authorized.');

        $query =
            <<<QUERY
{
  customer {
    wishlist {
      items_count
      sharing_code
      updated_at
    }
  }
}
QUERY;
        $this->graphQlQuery($query);
    }

    /**
     * @magentoConfigFixture default_store wishlist/general/active 0
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testCustomerCannotGetWishlistWhenDisabled()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The wishlist configuration is currently disabled.');

        $query =
            <<<QUERY
{
  customer {
    wishlist {
      items_count
      sharing_code
      updated_at
    }
  }
}
QUERY;
        $this->graphQlQuery(
            $query,
            [],
            '',
            $this->getCustomerAuthHeaders('customer@example.com', 'password')
        );
    }

    /**
     * @param string $email
     * @param string $password
     * @return array
     * @throws \Magento\Framework\Exception\AuthenticationException
     */
    private function getCustomerAuthHeaders(string $email, string $password): array
    {
        $customerToken = $this->customerTokenService->createCustomerAccessToken($email, $password);
        return ['Authorization' => 'Bearer ' . $customerToken];
    }
}
