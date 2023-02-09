<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool\Model;

enum ServerEventType {
    case LISTENING;
    case CLIENT_CONNECTED;
    case CLIENT_DISCONNECTED;
    case CLIENT_DATA_RECEIVED;
    case PROCESSING_MESSAGE;
    case PROCESS_ALREADY_RUNNING;
    case PROCESS_ALREADY_QUEUED;
    case PROCESS_STARTED;
    case PROCESS_TERMINATED;
    case ERROR;
}
