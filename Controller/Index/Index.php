<?php
 
namespace Knowpapa\Sourcing\Controller\Index;
 
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Escaper;
use Knowpapa\Sourcing\Model\Mail\TransportBuilder;

    
class Index extends \Magento\Framework\App\Action\Action {
    protected $_resultPageFactory;
    protected $sessionFactory;
    protected $_transportBuilder;
    protected $_storeManager;
    protected $_escaper;
    
    protected $directoryList;
    protected $fileSystem;
    protected $allowedExtensions = array('jpg', 'jpeg', 'gif', 'png','pdf', 'docx', 'doc');
    protected $allFileUrls = []; 
    
    public function __construct(Context $context, PageFactory $resultPageFactory,
        TransportBuilder $transportBuilder, StoreManagerInterface $storeManager,
        Escaper $escaper, UploaderFactory $uploaderFactory, Filesystem $fileSystem,
        DirectoryList $directoryList
        )
      {
        $this->_resultPageFactory = $resultPageFactory;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->sessionFactory = $objectManager->get('Magento\Customer\Model\Session');
        $this->_transportBuilder = $transportBuilder;
        $this->_storeManager = $storeManager;
        $this->_escaper = $escaper;
        
        $this->uploaderFactory = $uploaderFactory;
        $this->fileSystem = $fileSystem;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }
 
    public function execute(){
      if(!$this->sessionFactory->isLoggedIn()) {
         $this->messageManager->addNotice(__('You must be logged in to post a sourcing request.'));
         $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
         $result->setPath('customer/account/login/',['redirect' => 'sourcing']);
         return $result;  
        }
      $post = (array) $this->getRequest()->getPost();  
      if (!empty($post)) {
        
        try {
            $customerId = $this->sessionFactory->getCustomer()->getId();  
            $customerName = $this->sessionFactory->getCustomer()->getName() ?? 'Customer';
            $customerEmail = $this->sessionFactory->getCustomer()->getEmail();
            $attachmentCount = $post['attachmentCount'];
            $this->uploadAllFiles($attachmentCount);
            $this->sendMail($post, $customerId,$customerEmail, $customerName,$attachmentCount);
            $this->messageManager->addSuccessMessage(__('Thanks for requesting a quote. We\'ll get back to you very soon.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            //echo($e);
            $this->messageManager->addErrorMessage(__('An error occurred while processing your form. Please try again later.'));
        }
     }
     $resultPage = $this->_resultPageFactory->create();
     $resultPage->getConfig()->getTitle()->set(__('Source products or solutions'));
     return $resultPage;

  }
    
    private function uploadAllFiles($attachmentCount){
      for ($k = 1 ; $k <= $attachmentCount; $k++){ 
          $fileFormName = 'sourcingFileAttachment'.$k;
          if($_FILES[$fileFormName]["error"] != 4) {
          $name = $_FILES[$fileFormName]['name'];
          $tmpName = $_FILES[$fileFormName]['tmp_name'];
          $this->uploadSingleFile($name, $tmpName);
          }
        }
     }       
            
    private function uploadSingleFile($name, $tmpName){
      $uploaddir = '/var/www/html/pub/media/sourcing/';
      $uploadfile = $uploaddir . basename($name);
      $result = move_uploaded_file($tmpName, $uploadfile);
      //if ($result) {} else {}
    }
    
            
    private function sendMail($post, $customerId, $customerEmail, $customerName, $attachmentCount){
        $store = $this->_storeManager->getStore()->getId();
        $post = (array) $this->getRequest()->getPost();
        $sender = [ 'name' => 'HCX Sourcing', 'email' => 'admin@hcx.global'];
        $temptransport = $this->_transportBuilder->setTemplateIdentifier('custom_mail_template')
            ->setTemplateOptions(['area' => 'frontend', 'store' => $store])
            ->setTemplateVars(
                [
                    'customerEmail' => $customerEmail,
                    'customeId' => $customerId,
                    'customeName' => $customerName,
                    'store' => $this->_storeManager->getStore(),
                    'keywords'    => $this->_escaper->escapeHtml($post['keywords']),
                    'quantity'    => $this->_escaper->escapeHtml($post['quantity']),
                    'location'    => $this->_escaper->escapeHtml($post['location']),
                    'specification' => $this->_escaper->escapeHtml($post['specification'])
                ]
            )
            ->setFrom($sender)
            ->addTo('rakeyshchandan@gmail.com', 'Bhaskar Chaudhary');
            //->addCc($customerEmail,$customerName );
            for ($k = 1 ; $k <= $attachmentCount; $k++){ 
              $fileFormName = 'sourcingFileAttachment'.$k;
              if($_FILES[$fileFormName]["error"] != 4) {
                  $name = $_FILES[$fileFormName]['name'];  
                  $mimeType = $_FILES[$fileFormName]['type'];
                  $uploadedFile = '/var/www/html/pub/media/sourcing/'.$name;
                  $temptransport->addAttachment(file_get_contents($uploadedFile), $name, $mimeType); 
               }           
            }
        $transport = $temptransport->getTransport();            
        $transport->sendMessage();  
      }
    
} 
