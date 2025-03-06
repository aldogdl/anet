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
    // Action Cotizacion de Proveedor a Proveedor (Whatsapp puro)
    case COTNOWFRM = 'cotNowFrm';
    // Action Cotizacion de Proveedor a Proveedor (Formulario App)
    case COTNOWWA = 'cotNowWa';
    case COMMAND = 'cmd';
}
