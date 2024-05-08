<?php

namespace App\Enums;

enum TypesWaMsgs: String
{
    case STT = 'stt';
    case DOC = 'doc';
    case LOGIN = 'login';
    case IMAGE = 'image';
    case TEXT = 'text';
    case INTERACTIVE = 'interactive';
    case BTNCOTNOW = 'btnCotNow';
    case NTG = 'ntg';
    case NTGA = 'ntga';
    case COMMAND = 'cmd';
}
