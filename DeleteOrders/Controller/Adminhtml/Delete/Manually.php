<?php
/**
 * @category   Inventionstar
 * @package    Inventionstar_DeleteOrders
 * @author     guptapankaj775@gmail.com
 * @copyright  This file was generated by using Module Creator(http://code.vky.co.in/magento-2-module-creator/) provided by VKY <viky.031290@gmail.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Inventionstar\DeleteOrders\Controller\Adminhtml\Delete;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\StoreManagerInterface;
use Inventionstar\DeleteOrders\Helper\Data as HelperData;
use Inventionstar\DeleteOrders\Helper\Email;
use Psr\Log\LoggerInterface;

/**
 * Class Manually
 *
 * @package Inventionstar\DeleteOrders\Controller\Adminhtml\Delete
 */
class Manually extends Action
{
    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderManagementInterface
     */
    protected $_orderManagement;

    /**
     * @var Email
     */
    protected $_email;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Manually constructor.
     *
     * @param Context $context
     * @param HelperData $helperData
     * @param OrderRepository $orderRepository
     * @param LoggerInterface $logger
     * @param OrderManagementInterface $orderManagement
     * @param Email $email
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        HelperData $helperData,
        OrderRepository $orderRepository,
        LoggerInterface $logger,
        OrderManagementInterface $orderManagement,
        Email $email,
        StoreManagerInterface $storeManager
    ) {
        $this->_helperData      = $helperData;
        $this->orderRepository  = $orderRepository;
        $this->logger           = $logger;
        $this->_orderManagement = $orderManagement;
        $this->_email           = $email;
        $this->_storeManager    = $storeManager;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $resultRedirect  = $this->resultRedirectFactory->create();
        $storeId         = $this->getRequest()->getParam('store');
        $status          = ['processing', 'pending', 'fraud'];
        $orderCollection = $this->_helperData->getMatchingOrders($storeId);

        if ($orderCollection->getSize()) {
            $successDelete = 0;
            $errorOrders   = [];

            foreach ($orderCollection->getItems() as $order) {
                try {
                    if ($this->_helperData->versionCompare('2.3.0')) {
                        if (in_array($order->getStatus(), $status, true)) {
                            $this->_orderManagement->cancel($order->getId());
                        }
                        if ($order->getStatus() === 'holded') {
                            $this->_orderManagement->unHold($order->getId());
                            $this->_orderManagement->cancel($order->getId());
                        }
                    }

                    $this->orderRepository->delete($order);
                    $this->_helperData->deleteRecord($order->getId());
                    $successDelete++;
                } catch (Exception $e) {
                    $errorOrders[$order->getId()] = $order->getIncrementId();
                    $this->logger->error($e->getMessage());
                }
            }

            if ($successDelete) {
                $this->messageManager->addSuccessMessage(__('Success! ' . $successDelete . ' orders have been deleted'));
            }

            if (count($errorOrders)) {
                $this->messageManager->addErrorMessage(__(
                    'The following orders cannot being deleted. %1',
                    implode(', ', $errorOrders)
                ));
            }
        } else {
            $this->messageManager->addNoticeMessage(__('No order has been deleted!'));
        }

        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
