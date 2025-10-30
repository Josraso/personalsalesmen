<?php
/**
 * Mail Notification Hook
 * Handles email notifications to employees when their assigned customers place orders
 */

declare(strict_types=1);

namespace PrestaShop\Module\PersonalSalesmen\Hook;

use PrestaShop\Module\PersonalSalesmen\Service\AssignmentService;
use Configuration;
use Context;
use Mail;
use Employee;
use Customer;
use Order;
use Currency;
use Tools;
use Language;

class MailNotificationHook
{
    private AssignmentService $assignmentService;
    private Context $context;

    public function __construct(AssignmentService $assignmentService, ?Context $context = null)
    {
        $this->assignmentService = $assignmentService;
        $this->context = $context ?? Context::getContext();
    }

    /**
     * Hook: actionValidateOrder
     * Triggered when a new order is validated
     *
     * @param array $params
     * @return void
     */
    public function onOrderValidation(array $params): void
    {
        // Verificar si las notificaciones están habilitadas
        if (!Configuration::get('PSM_EMAIL_NOTIFICATIONS')) {
            return;
        }

        // Verificar que tenemos los datos necesarios
        if (!isset($params['customer']) || !isset($params['order'])) {
            return;
        }

        $customer = $params['customer'];
        $order = $params['order'];

        // Validar objetos
        if (!$customer instanceof Customer || !$order instanceof Order) {
            return;
        }

        // Obtener empleados asignados a este cliente
        $employees = $this->assignmentService->getAssignedEmployees((int)$customer->id);

        if (empty($employees)) {
            return;
        }

        // Enviar notificación a cada empleado
        foreach ($employees as $employee) {
            if ($employee instanceof Employee && $employee->active) {
                $this->sendOrderNotification($employee, $customer, $order);
            }
        }
    }

    /**
     * Send email notification to employee
     *
     * @param Employee $employee
     * @param Customer $customer
     * @param Order $order
     * @return bool
     */
    private function sendOrderNotification(Employee $employee, Customer $customer, Order $order): bool
    {
        // Validar email del empleado
        if (empty($employee->email) || !\Validate::isEmail($employee->email)) {
            return false;
        }

        // Obtener idioma del empleado o idioma por defecto
        $idLang = (int)($employee->id_lang ?? Configuration::get('PS_LANG_DEFAULT'));
        $language = new Language($idLang);

        if (!$language->active) {
            $idLang = (int)Configuration::get('PS_LANG_DEFAULT');
            $language = new Language($idLang);
        }

        // Preparar variables del template
        $templateVars = $this->prepareTemplateVars($employee, $customer, $order);

        // Intentar enviar el email
        try {
            $result = Mail::send(
                $idLang,
                'new_order_assigned',
                $this->getEmailSubject($idLang),
                $templateVars,
                $employee->email,
                $employee->firstname . ' ' . $employee->lastname,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'personalsalesmen/views/templates/emails/',
                false,
                $this->context->shop->id
            );

            // Log si falla
            if (!$result) {
                \PrestaShopLogger::addLog(
                    sprintf(
                        'PersonalSalesmen: Failed to send email to %s for order #%d',
                        $employee->email,
                        $order->id
                    ),
                    2,
                    null,
                    'Order',
                    $order->id
                );
            }

            return $result;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog(
                sprintf(
                    'PersonalSalesmen: Exception sending email to %s: %s',
                    $employee->email,
                    $e->getMessage()
                ),
                3,
                null,
                'Order',
                $order->id
            );
            return false;
        }
    }

    /**
     * Prepare template variables for email
     *
     * @param Employee $employee
     * @param Customer $customer
     * @param Order $order
     * @return array
     */
    private function prepareTemplateVars(Employee $employee, Customer $customer, Order $order): array
    {
        $currency = new Currency($order->id_currency);

        return [
            '{employee_name}' => $employee->firstname . ' ' . $employee->lastname,
            '{employee_firstname}' => $employee->firstname,
            '{employee_lastname}' => $employee->lastname,
            '{customer_name}' => $customer->firstname . ' ' . $customer->lastname,
            '{customer_firstname}' => $customer->firstname,
            '{customer_lastname}' => $customer->lastname,
            '{customer_email}' => $customer->email,
            '{order_reference}' => $order->reference,
            '{order_id}' => $order->id,
            '{order_total}' => Tools::displayPrice($order->total_paid_tax_incl, $currency),
            '{order_total_products}' => Tools::displayPrice($order->total_products, $currency),
            '{order_shipping}' => Tools::displayPrice($order->total_shipping, $currency),
            '{order_date}' => Tools::displayDate($order->date_add, null, true),
            '{order_link}' => $this->getOrderBackofficeLink($order->id),
            '{customer_link}' => $this->getCustomerBackofficeLink($customer->id),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => Tools::getShopDomainSsl(true),
            '{date_full}' => date('Y'),
        ];
    }

    /**
     * Get order backoffice link
     *
     * @param int $orderId
     * @return string
     */
    private function getOrderBackofficeLink(int $orderId): string
    {
        if (!isset($this->context->link)) {
            return '';
        }

        try {
            return $this->context->link->getAdminLink('AdminOrders', true, [], [
                'id_order' => $orderId,
                'vieworder' => 1
            ]);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get customer backoffice link
     *
     * @param int $customerId
     * @return string
     */
    private function getCustomerBackofficeLink(int $customerId): string
    {
        if (!isset($this->context->link)) {
            return '';
        }

        try {
            return $this->context->link->getAdminLink('AdminCustomers', true, [], [
                'id_customer' => $customerId,
                'viewcustomer' => 1
            ]);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get email subject based on language
     *
     * @param int $idLang
     * @return string
     */
    private function getEmailSubject(int $idLang): string
    {
        $language = new Language($idLang);

        // Mapeo de idiomas (puedes extender esto)
        $subjects = [
            'es' => 'Nuevo pedido de tu cliente asignado',
            'en' => 'New order from your assigned customer',
            'fr' => 'Nouvelle commande de votre client assigné',
            'it' => 'Nuovo ordine dal tuo cliente assegnato',
            'de' => 'Neue Bestellung von Ihrem zugewiesenen Kunden',
            'pt' => 'Novo pedido do seu cliente atribuído',
        ];

        $isoCode = $language->iso_code ?? 'en';

        return $subjects[$isoCode] ?? $subjects['en'];
    }

    /**
     * Send test email (for debugging)
     *
     * @param int $employeeId
     * @param int $customerId
     * @param int $orderId
     * @return bool
     */
    public function sendTestEmail(int $employeeId, int $customerId, int $orderId): bool
    {
        $employee = new Employee($employeeId);
        $customer = new Customer($customerId);
        $order = new Order($orderId);

        if (!\Validate::isLoadedObject($employee) ||
            !\Validate::isLoadedObject($customer) ||
            !\Validate::isLoadedObject($order)) {
            return false;
        }

        return $this->sendOrderNotification($employee, $customer, $order);
    }

    /**
     * Get notification statistics
     *
     * @param int $employeeId
     * @return array
     */
    public function getNotificationStats(int $employeeId): array
    {
        // Esta función puede expandirse para trackear estadísticas de notificaciones
        return [
            'enabled' => (bool)Configuration::get('PSM_EMAIL_NOTIFICATIONS'),
            'employee_id' => $employeeId,
            'employee_has_assignments' => count($this->assignmentService->getStatistics()) > 0
        ];
    }
}
