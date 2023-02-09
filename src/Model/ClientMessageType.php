<?php
declare(strict_types=1);
namespace Wwwision\ChildProcessPool\Model;

enum ClientMessageType: string {
    case STATUS = 'status';
    case RUN = 'run';
}
