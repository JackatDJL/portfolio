@php
use App\Support\Latex;
use App\Support\ApplicationLetter;
$e = fn ($value) => new \Illuminate\Support\HtmlString(Latex::escape((string) $value));
$u = fn ($value) => new \Illuminate\Support\HtmlString(ApplicationLetter::url((string) $value));
@endphp
% DIN 5008-oriented A4 letter, form B address zone. Not a compliance certification.
\documentclass[11pt,a4paper]{article}
\usepackage[a4paper,left=25mm,right=20mm,top=25mm,bottom=22mm,footskip=10mm]{geometry}
\usepackage[ngerman]{babel}
\usepackage{fontspec,xcolor,hyperref,tikz,needspace}
\setmainfont{Fira Sans}[Path=resources/fonts/,Extension=.ttf,UprightFont=FiraSans-Regular,BoldFont=FiraSans-Bold,ItalicFont=FiraSans-Regular,ItalicFeatures={FakeSlant=0.18}]
\definecolor{accent}{HTML}{ {{ $letter['accent'] }} }
\definecolor{ink}{HTML}{202624}
\color{ink}
\hypersetup{colorlinks=true,urlcolor=accent,linkcolor=accent,pdfauthor={ {{ $e($letter['sender']) }} },pdftitle={ {{ $e($letter['subject']) }} }}
\setlength{\parindent}{0pt}
\setlength{\parskip}{2.5mm}
\setlength{\leftmargini}{5mm}
\emergencystretch=2em
\raggedright
\pagestyle{plain}
\begin{document}
\begin{tikzpicture}[remember picture,overlay]
\node[anchor=north east,inner sep=0pt,text width=165mm,align=right] at ([xshift=-20mm,yshift=-18mm]current page.north east) {\textbf{ {{ $e($letter['sender']) }} }\\[-.4mm]{\fontsize{9}{11}\selectfont {{ $e($letter['address']) }} @if($letter['email'])\\\href{ {{ $u('mailto:'.$letter['email']) }} }{ {{ $e($letter['email']) }} }@endif @if($letter['phone'])\quad {{ $e($letter['phone']) }}@endif}};
\draw[accent,line width=.6pt] ([xshift=25mm,yshift=-39mm]current page.north west) -- ([xshift=-20mm,yshift=-39mm]current page.north east);
\node[anchor=north west,inner sep=0pt,text width=85mm,align=left] at ([xshift=25mm,yshift=-46mm]current page.north west) { {\fontsize{7.5}{9}\selectfont {{ $e($letter['sender']) }} · {{ $e($letter['return_address']) }} }\\[3mm]@foreach($letter['recipient'] as $line)
{{ $e($line) }}
@if(!$loop->last)
\\
@endif
@endforeach};
\end{tikzpicture}
% Real destinations let the composer retarget attachment links to their final pages.
\hypertarget{application-cv}{}@foreach($letter['documents'] as $label)\hypertarget{application-document-{{ $loop->iteration }}}{}@endforeach
\vspace*{66mm}
\begin{flushright}{{ $e($letter['date']) }}\end{flushright}
\vspace{4mm}
{\bfseries {{ $e($letter['subject']) }}\par}
\vspace{3mm}
{{ $e($letter['salutation']) }}\par
\vspace{1mm}
{!! $letter['body'] !!}
\Needspace{28mm}
\vspace{2mm}
{{ $e($letter['closing']) }}\par
@if($letter['signature'])\vspace{6mm}{{ $e($letter['signature']) }}\par
@endif
\vspace{5mm}
{\fontsize{9}{11}\selectfont\textbf{Anlagen}\par
\hyperlink{application-cv}{Lebenslauf}@foreach($letter['documents'] as $label)\\\hyperlink{application-document-{{ $loop->iteration }}}{ {{ $e($label) }} }@endforeach\par}
\end{document}
