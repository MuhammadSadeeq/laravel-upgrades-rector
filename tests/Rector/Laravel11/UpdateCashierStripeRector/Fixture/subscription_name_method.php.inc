<?php

class WebhookHandler
{
    public function newSubscriptionName($payload)
    {
        return $payload['data']['object']['metadata']['subscription_name'] ?? null;
    }

    public function handleWebhook()
    {
        $name = $this->newSubscriptionName($payload);
    }
}

?>
-----
<?php

class WebhookHandler
{
    public function newSubscriptionName($payload)
    {
        return $payload['data']['object']['metadata']['subscription_name'] ?? null;
    }

    public function handleWebhook()
    {
        $name = $this->newSubscriptionType($payload);
    }
}

?>