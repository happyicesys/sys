import{_ as F}from"./Authenticated.7db80fdf.js";import{_ as v}from"./Button.23a05acd.js";import O from"./Form.a896c842.js";import{_ as U}from"./Paginator.7c2c62b7.js";import{_ as T,r as A}from"./SearchInput.bc3567b9.js";import{_ as D}from"./MultiSelect.2415f05c.js";import{_ as c,a as d}from"./TableData.b7faba11.js";import{_ as Q}from"./TableHeadSort.124fba78.js";import{g as h,j as Z,h as q,f as b,a as t,u as m,w as n,F as B,o as f,Z as z,b as e,d as l,t as i,k as G,l as C,c as $,K as H,O as S}from"./app.8d489fd7.js";import J from"./ExchangeRate.62ed1db6.js";import{r as W}from"./PlusIcon.77a7c9ec.js";import{r as X}from"./BackspaceIcon.2558c7fa.js";import{r as Y}from"./PlusCircleIcon.829bff36.js";import{r as ee}from"./PencilSquareIcon.5e700db8.js";import"./open-closed.13f31f1e.js";import"./use-resolve-button-type.add6567f.js";import"./RectangleStackIcon.71077489.js";import"./FormInput.b01b0517.js";import"./Modal.a8b67aa4.js";import"./CheckCircleIcon.7b2c8429.js";import"./ArrowUturnLeftIcon.6dcf8b2c.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.a2e98afe.js";const te=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Country & Currency) ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},ae={class:"flex justify-end"},le=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},ue={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),he={class:"font-medium"},xe=e("span",null,"to",-1),pe={class:"font-medium"},_e=e("span",null,"of",-1),ve={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Le={class:"divide-x divide-gray-200"},Pe=e("br",null,null,-1),Be={class:"bg-white"},Se={class:"flex justify-center space-x-1"},Me=e("span",null," Rate ",-1),Ve=e("span",null," Edit ",-1),Ie={key:0},Ne=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[Ne],st={__name:"Index",props:{countries:Object},setup(a){const r=h({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),x=h(!1),y=h(!1),p=h(),_=h(""),w=h([]),M=Z(()=>H().props.auth.user);q(()=>{w.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],r.value.numberPerPage=w.value[0]});function V(){_.value="create",p.value=null,x.value=!0}function I(g){_.value="update",p.value=g,x.value=!0}function N(g){_.value="update",p.value=g,y.value=!0}function k(){S.get("/countries",{...r.value,numberPerPage:r.value.numberPerPage.id},{preserveState:!0,replace:!0})}function E(){S.get("/countries")}function R(g){r.value.sortKey=g,r.value.sortBy=!r.value.sortBy,k()}function K(){x.value=!1}return(g,o)=>(f(),b(B,null,[t(m(z),{title:"Country"}),t(F,null,{header:n(()=>[te]),default:n(()=>{var L,P;return[e("div",ne,[e("div",se,[e("div",ae,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=s=>V())},{default:n(()=>[t(m(W),{class:"h-4 w-4","aria-hidden":"true"}),le]),_:1})]),e("div",oe,[t(T,{placeholderStr:"Name",modelValue:r.value.name,"onUpdate:modelValue":o[1]||(o[1]=s=>r.value.name=s)},{default:n(()=>[l(" Name ")]),_:1},8,["modelValue"])]),e("div",re,[e("div",ie,[e("div",ue,[t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=s=>k())},{default:n(()=>[t(m(A),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(v,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=s=>E())},{default:n(()=>[t(m(X),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",fe,[ge,e("span",he,i((L=a.countries.meta.from)!=null?L:0),1),xe,e("span",pe,i((P=a.countries.meta.to)!=null?P:0),1),_e,e("span",ve,i(a.countries.meta.total),1),ye]),t(D,{modelValue:r.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=s=>r.value.numberPerPage=s),options:w.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:k},null,8,["modelValue","options"])])])]),e("div",be,[e("div",Ce,[e("div",we,[e("table",ke,[e("thead",$e,[e("tr",Le,[t(c,null,{default:n(()=>[l(" # ")]),_:1}),t(Q,{modelName:"name",sortKey:r.value.sortKey,sortBy:r.value.sortBy,onSortTable:o[5]||(o[5]=s=>R("name"))},{default:n(()=>[l(" Name ")]),_:1},8,["sortKey","sortBy"]),t(c,null,{default:n(()=>[l(" Code ")]),_:1}),t(c,null,{default:n(()=>[l(" Currency ")]),_:1}),t(c,null,{default:n(()=>[l(" Symbol ")]),_:1}),t(c,null,{default:n(()=>[l(" Phone Code ")]),_:1}),t(c,null,{default:n(()=>[l(" Latest Rate "),Pe,l(" (Base: "+i(m(M).profile.base_currency.currency_name)+") ",1)]),_:1}),t(c)])]),e("tbody",Be,[(f(!0),b(B,null,G(a.countries.data,(s,u)=>(f(),b("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(a.countries.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.currency_name),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.currency_symbol),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[l(i(s.phone_code),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-right"},{default:n(()=>[l(i(s.latestQuoteExchangeRate?s.latestQuoteExchangeRate.rate:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(d,{currentIndex:u,totalLength:a.countries.length,inputClass:"text-center"},{default:n(()=>[e("div",Se,[t(v,{type:"button",class:"bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1",onClick:j=>N(s)},{default:n(()=>[t(m(Y),{class:"h-4 w-4","aria-hidden":"true"}),Me]),_:2},1032,["onClick"]),t(v,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:j=>I(s)},{default:n(()=>[t(m(ee),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.countries.data.length?C("",!0):(f(),b("tr",Ie,Ee))])]),a.countries.data.length?(f(),$(U,{key:0,links:a.countries.links,meta:a.countries.meta},null,8,["links","meta"])):C("",!0)])])])]),x.value?(f(),$(O,{key:0,country:p.value,type:_.value,showModal:x.value,onModalClose:K},null,8,["country","type","showModal"])):C("",!0),y.value?(f(),$(J,{key:1,country:p.value,type:_.value,showModal:y.value,onModalClose:o[6]||(o[6]=s=>y.value=!1)},null,8,["country","type","showModal"])):C("",!0)]}),_:1})],64))}};export{st as default};
