@php
use App\Support\Latex;
use App\Support\ApplicationLetter;
$e = fn ($value) => new \Illuminate\Support\HtmlString(Latex::escape((string) $value));
$u = fn ($value) => new \Illuminate\Support\HtmlString(ApplicationLetter::url((string) $value));
$subject = new \Illuminate\Support\HtmlString(implode('\\\\', array_map(fn ($line) => Latex::escape($line), preg_split('/\R/u', $letter['subject']))));
@endphp
% Plain German letter layout. Letter-only font assets deliberately live outside the CV font directory.
\documentclass[11pt,a4paper]{article}
\usepackage[a4paper,left=25mm,right=25mm,top=25mm,bottom=25mm]{geometry}
\usepackage[ngerman]{babel}
\usepackage{fontspec,xcolor,hyperref,tikz,needspace,graphicx}
\setmainfont{Noto Sans SemiCondensed}[Path=resources/application-fonts/,Extension=.ttf,UprightFont=NotoSans-SemiCondensed,BoldFont=NotoSans-SemiCondensedBold,ItalicFont=NotoSans-SemiCondensedItalic]
\color{black}
\hypersetup{colorlinks=true,urlcolor=blue,linkcolor=black,pdfauthor={ {{ $e($letter['sender']) }} },pdftitle={ {{ $e($letter['subject']) }} }}
\setlength{\parindent}{0pt}
\setlength{\parskip}{6pt}
\setlength{\leftmargini}{5mm}
\emergencystretch=2em
\raggedright
\pagestyle{empty}
\newsavebox{\applicationrecipientbox}
\begin{document}
% Measure the actual typeset recipient block, including automatic line wrapping.
\sbox{\applicationrecipientbox}{\begin{minipage}{85mm}\setlength{\parskip}{0pt}
@foreach($letter['recipient'] as $line)
{{ $e($line) }}@if(!$loop->last)\\@endif
@endforeach
\end{minipage}}
\ifdim\dimexpr\ht\applicationrecipientbox+\dp\applicationrecipientbox\relax>24mm
\typeout{APPLICATION_RECIPIENT_ADDRESS_OVERFLOW}
\PackageError{Application letter}{Recipient address exceeds its DIN 5008 address zone}{Shorten the address or move some lines to a separate field.}
\fi
\begin{tikzpicture}[remember picture,overlay]
\node[anchor=north east,inner sep=0pt,outer sep=0pt,text width=80mm,align=right,font=\small] at ([xshift=-25mm,yshift=-26.2mm]current page.north east) { {{ $e($letter['sender']) }}
@foreach($letter['sender_address_lines'] as $line)
\\ {{ $e($line) }}
@endforeach
@if($letter['phone'])
\\ Tel.: {{ $e($letter['phone']) }}
@endif
@if($letter['email'])
\\\href{ {{ $u('mailto:'.$letter['email']) }} }{ {{ $e($letter['email']) }} }
@endif
};
\node[anchor=north west,inner sep=0pt,outer sep=0pt] at ([xshift=25mm,yshift=-57.4mm]current page.north west) {\usebox{\applicationrecipientbox}};
\node[anchor=base east,inner sep=0pt,outer sep=0pt] at ([xshift=-25mm,yshift=-84.73mm]current page.north east) { {{ $e($letter['date_line']) }} };
\end{tikzpicture}
% Destinations stay available for author-created Bard links and composite retargeting.
\hypertarget{application-cv}{}@foreach($letter['documents'] as $label)\hypertarget{application-document-{{ $loop->iteration }}}{}@endforeach
\par
\vspace*{61mm}
{\bfseries {!! $subject !!}\par}
\vspace{10pt}
{{ $e($letter['salutation']) }}\par
{!! $letter['body'] !!}
\Needspace{28mm}
{{ $e($letter['closing']) }}\par
@if($letter['signature'])
\vspace{11mm}
{{ $e($letter['signature']) }}\par
@elseif($letter['signature_image'])
\vspace{7mm}
\begin{minipage}{\textwidth}
@if($letter['signature_alignment'] === 'center')\centering @elseif($letter['signature_alignment'] === 'right')\raggedleft @else\raggedright @endif
\raisebox{ {{ (int) $letter['signature_baseline_mm'] }}mm }{\includegraphics[width={{ (int) $letter['signature_width_mm'] }}mm,height=16mm,keepaspectratio]{ {{ $e($letter['signature_image']) }} }}\par
{{ $e($letter['sender']) }}\par
\end{minipage}
@endif
\end{document}
