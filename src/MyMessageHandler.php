<?php

namespace App;

class MyMessageHandler
{
    public function __invoke(MyMessage $message)
    {
        echo "Handling message: ".$message->message."\n";
    }
}
