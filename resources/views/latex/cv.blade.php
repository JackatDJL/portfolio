@php
use App\Support\Latex;
$e = fn ($value) => new \Illuminate\Support\HtmlString(Latex::escape((string) $value));
$u = fn ($value) => new \Illuminate\Support\HtmlString(strtr((string) $value, ['\\' => '\%5C', '{' => '\%7B', '}' => '\%7D', '%' => '\%', '#' => '\#', '~' => '\%7E']));
$compact = count($cv['projects']) <= 3;
@endphp
% !TeX program = lualatex
\documentclass[9pt,a4paper]{article}
\usepackage[a4paper,top=18mm,bottom=18mm,left=17mm,right=17mm,headheight=7mm,headsep=4mm]{geometry}
\usepackage{fontspec,xcolor,graphicx,hyperref,tikz,array,tabularx,paracol,needspace}
\usetikzlibrary{calc}
\setmainfont{Fira Sans}[Path=resources/fonts/,Extension=.ttf,UprightFont=FiraSans-Regular,BoldFont=FiraSans-Bold]
\definecolor{accent}{HTML}{@php echo strtoupper(ltrim($cv['accent'], '#')); @endphp}
\definecolor{cvtext}{HTML}{111A16}\definecolor{muted}{HTML}{52605A}\definecolor{rule}{HTML}{CCD8D2}\definecolor{paper}{HTML}{FBFCFB}
\colorlet{linkaccent}{accent!62!cvtext}
\pagecolor{paper}\color{cvtext}
\hypersetup{colorlinks=true,urlcolor=linkaccent,linkcolor=linkaccent,pdfauthor={ {{ $e($cv['name']) }} },pdftitle={ {{ $e($cv['name']) }} · Lebenslauf @if($cv['profile']) · {{ $e($cv['profile']['title']) }}@endif }}
\setlength{\parindent}{0pt}\setlength{\parskip}{0pt}\setlength{\columnsep}{7mm}\emergencystretch=1em
\makeatletter\def\ps@cv{\def\@oddhead{\footnotesize\bfseries {{ $e($cv['name']) }} · Lebenslauf\hfill\scriptsize\color{muted}\thepage}\let\@evenhead\@oddhead\def\@oddfoot{}\def\@evenfoot{}}\makeatother\pagestyle{cv}
\newcommand{\cvlink}[2]{\href{#1}{\textcolor{linkaccent}{\underline{#2}}}}
\newcommand{\sectiontitle}[1]{\Needspace{30mm}\vspace{2.2mm}{\fontsize{15}{16}\selectfont\bfseries #1}\par\nopagebreak\vspace{1mm}{\color{rule}\rule{\linewidth}{.45pt}}\par\nopagebreak\vspace{.4mm}}
\newcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{11}{13}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{30mm}@{}}\strut\textbf{\cvlink{#4}{#1}}&\strut{\fontsize{8.2}{13}\selectfont\color{muted}#2}\\[-.8mm]\end{tabularx}\par{\fontsize{9}{11}\selectfont\bfseries #3}\par\vspace{.8mm}{\fontsize{9.5}{12.2}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{4mm}}
\newcommand{\milestoneentry}[4]{\noindent\begin{minipage}{\linewidth}\fontsize{8.4}{10.2}\selectfont\textbf{#1}\hfill{\fontsize{7}{9}\selectfont\color{muted}#2}\par\ifx\relax#3\relax\else{\fontsize{7.4}{9}\selectfont\bfseries #3}\par\fi\vspace{.4mm}\color{muted}#4\end{minipage}\par\vspace{2mm}}
\newcommand{\sidebarhead}[1]{\vspace{1mm}{\fontsize{7}{8}\selectfont\bfseries\MakeUppercase{#1}}\par\vspace{1.2mm}}
\newcommand{\sidevalue}[1]{\begingroup\fontsize{8.4}{10.5}\selectfont\color{muted}\raggedright #1\par\endgroup\vspace{1.2mm}}
@if($compact)
\renewcommand{\entry}[5]{\noindent\begin{minipage}{\linewidth}\fontsize{8.3}{10}\selectfont\begin{tabularx}{\linewidth}{@{}>{\raggedright\arraybackslash}X>{\raggedleft\arraybackslash}p{28mm}@{}}\strut\textbf{\cvlink{#4}{#1}}&\strut{\fontsize{6.8}{10}\selectfont\color{muted}#2}\\[-1mm]\end{tabularx}\par{\fontsize{7}{8.5}\selectfont\bfseries #3}\par\vspace{.5mm}{\fontsize{7.2}{9}\selectfont\color{muted}\raggedright #5\par}\end{minipage}\par\vspace{1.8mm}}
@endif
\begin{document}\thispagestyle{empty}
\begin{tikzpicture}[x=1mm,y=1mm]
  \def\W{176}\def\H{46}
  \fill[accent!18] (0,0) rectangle (\W,\H);
  \fill[paper] (0,0)--(0,18)..controls(35,21)and(64,25)..(95,17)..controls(124,10)and(142,7)..(157,0)--cycle;
  \fill[accent!48] (126,0)--(\W,0)--(\W,20)..controls(169,15)and(151,11)..(141,6)--cycle;
  \draw[cvtext,line width=.6pt] (0,0) rectangle (\W,\H);
  @if($cv['profile'])\node[anchor=west,inner sep=0pt,text=cvtext] at (11,37) {\fontsize{7.5}{8}\selectfont\bfseries {{ $e($cv['profile']['title']) }}};@endif
  \node[anchor=west,inner sep=0pt] at (11,19) {\fontsize{32}{32}\selectfont\bfseries {{ $e($cv['name']) }}};
  \node[anchor=west,inner sep=0pt] at (11,8) {\fontsize{7}{8}\selectfont\bfseries {{ $e($cv['location']) }}};
  @if($cv['profile'])\node[anchor=west,inner sep=0pt] at (73,8) {\fontsize{7}{8}\selectfont\cvlink{ {{ $u($cv['interactive_url']) }} }{Interaktive Version}};@endif
  @if($cv['photo'])\begin{scope}\clip (151,23) circle (15mm);\node[inner sep=0pt] at (151,23){\includegraphics[height=34mm]{ {{ $e($cv['photo']) }} }};\end{scope}\draw[cvtext,line width=.6pt] (151,23) circle (15mm);@endif
\end{tikzpicture}
\vspace{4mm}
\columnratio{.27}\begin{paracol}{2}
\sidebarhead{Kontakt}
@if($cv['contact']['public_email'])\sidevalue{E-Mail: \cvlink{mailto:{{ $u($cv['contact']['public_email']) }}}{ {{ $e($cv['contact']['public_email']) }} }}@endif
@if($cv['contact']['email'])\sidevalue{Private E-Mail: \cvlink{mailto:{{ $u($cv['contact']['email']) }}}{ {{ $e($cv['contact']['email']) }} }}@else\sidevalue{Private E-Mail: Geschützte Angabe}@endif
@if($cv['contact']['phone'])\sidevalue{ {{ $e($cv['contact']['phone']) }} }@else\sidevalue{Geschützte Angabe}@endif
@if($cv['contact']['address'])\sidevalue{ {{ $e($cv['contact']['address']) }} }@else\sidevalue{Geschützte Angabe}@endif
\sidevalue{ {{ $e($cv['location']) }} }
@if($cv['about'])\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Über mich}\sidevalue{ {{ $e($cv['about']) }} }@endif
@foreach($cv['knowledge'] as $group)\vspace{1.2mm}\sidebarhead{ {{ $e(mb_strtoupper($group['category'] ?? '')) }} }@foreach(($group['items'] ?? []) as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\{\scriptsize {{ $e($item['context']) }} }@endif}@endforeach @endforeach
@if($cv['soft_skills'])\sidebarhead{Soft Skills}
@foreach($cv['soft_skills'] as $item)\sidevalue{\textbf{ {{ $e($item['label'] ?? '') }} }@if(!empty($item['context']))\\ {{ $e($item['context']) }}@endif }@endforeach
@endif
\vspace{2mm}{\color{rule}\rule{\linewidth}{.4pt}}\par\sidebarhead{Online}
@if($cv['contact']['website'])\sidevalue{\cvlink{ {{ $u($cv['contact']['website']) }} }{Website}}@endif
@if($cv['contact']['github'])\sidevalue{\cvlink{ {{ $u($cv['contact']['github']) }} }{GitHub}}@endif
@if($cv['contact']['codeberg'])\sidevalue{\cvlink{ {{ $u($cv['contact']['codeberg']) }} }{Codeberg}}@endif
\switchcolumn
\sectiontitle{Erfahrung}
@foreach($cv['experience'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['organisation']) }} }{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
\sectiontitle{Bildung}
@foreach($cv['education'] as $item)\entry{ {{ $e($item['programme'] ?: $item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['title']) }} }{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
@if($cv['projects'])\sectiontitle{Projekte}
@foreach($cv['projects'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{}{ {{ $u($item['url']) }} }{ {{ $e($item['summary']) }} }@endforeach
@endif
@if($cv['publications'])\sectiontitle{Veröffentlichungen}
@foreach($cv['publications'] as $item)\entry{ {{ $e($item['title']) }} }{ {{ $e($item['period']) }} }{ {{ $e($item['type']) }} }{ {{ $u($item['url']) }} }{}@endforeach
@endif
@if($cv['milestones'])\sectiontitle{Stationen}
@foreach($cv['milestones'] as $item)\milestoneentry{ {{ $e($item['title']) }} }{ {{ $e($item['date']) }} }{ {{ $e($item['organisation']) }} }{ {{ $e($item['summary']) }} }
@if($item['relations']){\fontsize{7}{8.5}\selectfont\color{muted}Bezüge: @foreach($item['relations'] as $relation)\cvlink{ {{ $u($relation['url']) }} }{ {{ $e($relation['title']) }} }@if(!$loop->last) · @endif @endforeach\par}@endif
@endforeach
@endif
\vfill{\fontsize{7.2}{8.5}\selectfont\color{muted}Interaktive Version: \cvlink{ {{ $u($cv['interactive_url']) }} }{öffnen}}
\end{paracol}
\end{document}
