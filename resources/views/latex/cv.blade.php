@php use App\Support\Latex; $e = fn($v) => new \Illuminate\Support\HtmlString(Latex::escape($v)); $compact = count($cv['projects']) <= 3; @endphp
% !TeX program = lualatex
\documentclass[9pt,a4paper]{article}
\usepackage[a4paper,top=10mm,bottom=12mm,left=11mm,right=11mm,headheight=7mm,headsep=4mm]{geometry}
\usepackage{fontspec,xcolor,graphicx,hyperref,tikz,array,tabularx,paracol,needspace}
\usetikzlibrary{calc}
\setmainfont{Fira Sans}[Path=resources/fonts/,Extension=.ttf,UprightFont=FiraSans-Regular,BoldFont=FiraSans-Bold]
\definecolor{accent}{HTML}{@php echo strtoupper(ltrim($cv['accent'], '#')); @endphp}
\definecolor{accentsoft}{HTML}{@php echo strtoupper(ltrim($cv['accent'], '#')); @endphp}
\definecolor{cvtext}{HTML}{111A16}\definecolor{muted}{HTML}{52605A}\definecolor{rule}{HTML}{CCD8D2}\definecolor{paper}{HTML}{FBFCFB}
\pagecolor{paper}\color{cvtext}
\hypersetup{colorlinks=true,urlcolor=cvtext,linkcolor=cvtext,pdfauthor={ {{ $e($cv['name']) }} },pdftitle={ {{ $e($cv['name']) }} · Lebenslauf @if($cv['profile']) · {{ $e($cv['profile']['title']) }}@endif }}
\setlength{\parindent}{0pt}\setlength{\parskip}{0pt}\setlength{\columnsep}{8mm}\emergencystretch=1em
\makeatletter\def\ps@cv{\def\@oddhead{\footnotesize\bfseries {{ $e($cv['name']) }} · Lebenslauf\hfill\scriptsize\color{muted}\thepage}\let\@evenhead\@oddhead\def\@oddfoot{}\def\@evenfoot{}}\makeatother\pagestyle{cv}
\newcommand{\sectiontitle}[1]{\Needspace{24mm}\vspace{2.2mm}{\fontsize{15}{16}\selectfont\bfseries #1}\par\nopagebreak\vspace{1mm}{\color{rule}\rule{\linewidth}{.45pt}}\par\nopagebreak\vspace{.4mm}}
\newcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{9}{11}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{30mm}@{}}\strut\textbf{\href{#4}{#1}}&\strut{\fontsize{7}{11}\selectfont\color{muted}#2}\\[-.8mm]\end{tabularx}\par{\fontsize{7.8}{10}\selectfont\bfseries #3}\par\vspace{.8mm}{\fontsize{8}{10.5}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{3.3mm}}
\newcommand{\sidebarhead}[1]{\vspace{1mm}{\fontsize{7}{8}\selectfont\bfseries\MakeUppercase{#1}}\par\vspace{1.2mm}}
\newcommand{\sidevalue}[1]{\begingroup\fontsize{7.1}{8.6}\selectfont\color{muted}#1\par\endgroup\vspace{1mm}}
@if($compact)
\renewcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{8.3}{10}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{28mm}@{}}\strut\textbf{\href{#4}{#1}}&\strut{\fontsize{6.8}{10}\selectfont\color{muted}#2}\\[-1mm]\end{tabularx}\par{\fontsize{7}{8.5}\selectfont\bfseries #3}\par\vspace{.5mm}{\fontsize{7.2}{9}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{1.8mm}}
@endif
\begin{document}\thispagestyle{empty}
\begin{tikzpicture}[x=1mm,y=1mm]
  \def\W{188}\def\H{49}
  \fill[accent!18] (0,0) rectangle (\W,\H);
  \fill[paper] (0,0)--(0,18)..controls(35,21)and(64,25)..(95,17)..controls(124,10)and(142,7)..(157,0)--cycle;
  \fill[accent!48] (126,0)--(\W,0)--(\W,20)..controls(169,15)and(151,11)..(141,6)--cycle;
  \draw[cvtext,line width=.6pt] (0,0) rectangle (\W,\H);
  @if($cv['profile'])\node[anchor=west,inner sep=0pt,text=accent] at (11,39) {\fontsize{7.5}{8}\selectfont\bfseries {{ $e($cv['profile']['title']) }}};@endif
  \node[anchor=west,inner sep=0pt] at (11,20) {\fontsize{32}{32}\selectfont\bfseries {{ $e($cv['name']) }}};
  \node[anchor=west,inner sep=0pt] at (11,9) {\fontsize{7}{8}\selectfont\bfseries {{ $e($cv['location']) }}};
  @if($cv['photo'])\begin{scope}\clip (161,25) circle (16mm);\node[inner sep=0pt] at (161,25){\includegraphics[width=36mm,height=36mm,keepaspectratio]{ {{ $e($cv['photo']) }} }};\end{scope}\draw[cvtext,line width=.6pt] (161,25) circle (16mm);@endif
\end{tikzpicture}
\vspace{5mm}
\columnratio{.225}\begin{paracol}{2}
\sidebarhead{Kontakt}
@if($cv['contact']['email'])\sidevalue{\href{mailto:{{ $e($cv['contact']['email']) }}}{ {{ $e($cv['contact']['email']) }} }}@else\sidevalue{Geschützte Angabe}@endif
@if($cv['contact']['phone'])\sidevalue{ {{ $e($cv['contact']['phone']) }} }@else\sidevalue{Geschützte Angabe}@endif
@if($cv['contact']['address'])\sidevalue{ {{ $e($cv['contact']['address']) }} }@else\sidevalue{Geschützte Angabe}@endif
\sidevalue{ {{ $e($cv['location']) }} }
@if($cv['about'])\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Über mich}\sidevalue{ {{ $e($cv['about']) }} }@endif
@foreach($cv['knowledge'] as $group)\vspace{1.2mm}\sidebarhead{ {{ mb_strtoupper($e($group['category'] ?? '')) }} }@foreach(($group['items'] ?? []) as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\{\scriptsize {{ $e($item['context']) }} }@endif}@endforeach @endforeach
@if($cv['soft_skills'])\sidebarhead{Soft Skills}
@foreach($cv['soft_skills'] as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\ {{ $e($item['context']) }}@endif }@endforeach
@endif
\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Online}
\sidevalue{\href{ {{ $cv['contact']['website'] }} }{Website}}\sidevalue{\href{ {{ $cv['contact']['github'] }} }{GitHub}}\sidevalue{\href{ {{ $cv['contact']['codeberg'] }} }{Codeberg}}
\switchcolumn
\sectiontitle{Erfahrung}
@foreach($cv['experience'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['organisation']) }} }{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }@endforeach
@if(!$compact)
\end{paracol}
\newpage
\renewcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{10}{13}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{32mm}@{}}\strut\textbf{\href{#4}{#1}}&\strut{\fontsize{8}{13}\selectfont\color{muted}#2}\\[-1mm]\end{tabularx}\par{\fontsize{9}{11}\selectfont\bfseries #3}\par\vspace{1mm}{\fontsize{9}{13}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{5mm}}
@endif
\sectiontitle{Bildung}
@foreach($cv['education'] as $item)\entry{ {{ $e($item['programme'] ?: $item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['title']) }} }{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }@endforeach
\sectiontitle{Projekte}
@foreach($cv['projects'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{}{ {{ $item['url'] }} }{ {{ $e($item['summary']) }} }@endforeach
\sectiontitle{Veröffentlichungen}
@foreach($cv['publications'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['type']) }} }{ {{ $item['url'] }} }{}@endforeach
\vspace{3mm}{\fontsize{7.2}{8.5}\selectfont\color{muted}Interaktive Version: \href{ {{ $cv['canonical_url'] }} }{ {{ $e(preg_replace('#^https?://#', '', $cv['canonical_url'])) }} }}
@if($compact)\end{paracol}@endif
\end{document}
