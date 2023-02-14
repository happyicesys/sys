import{_ as U}from"./Authenticated.19c6b9b7.js";import{_}from"./Button.8d1f33c7.js";import A from"./Form.356cceae.js";import{_ as E,r as H,T as k,a as R,b as h}from"./TableData.ef7b510a.js";import{_ as L}from"./MultiSelect.49aeced5.js";import{_ as J}from"./TableHeadSort.fe9243e9.js";import{i as c,j as q,o as i,g as m,a as t,b as f,w as n,F as C,H as z,d as e,t as d,m as N,p as $,c as I,f as g,J as P}from"./app.6eb9dfc5.js";import{r as G}from"./PlusIcon.0e9c47d0.js";import{r as Q}from"./BackspaceIcon.d890ea71.js";import{r as W}from"./PencilSquareIcon.5d29f892.js";import{r as X}from"./TrashIcon.26795327.js";import"./open-closed.516b3a20.js";import"./use-resolve-button-type.b1dd9f40.js";import"./RectangleStackIcon.cc30d851.js";import"./FormInput.d0b04b5a.js";import"./Modal.78f1b457.js";import"./ArrowUturnLeftIcon.9caec9ed.js";import"./CheckCircleIcon.8fb97bba.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Tags) ",-1),Z={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=g(" Name "),ne={class:"col-span-5 md:col-span-1"},le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customers ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},de={class:"flex space-x-1"},ue=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),pe={class:"font-medium"},xe=e("span",null,"to",-1),_e={class:"font-medium"},he=e("span",null,"of",-1),ve={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},we={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ce={class:"min-w-full border-separate",style:{"border-spacing":"0"}},$e={class:"bg-gray-100"},Pe={class:"divide-x divide-gray-200"},Ve=g(" # "),Be=g(" Name "),Se=g(" Customers "),Le={class:"bg-white"},Ne={class:"inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border bg-gray-200"},Ie={class:"flex justify-center space-x-1"},Te=e("span",null," Edit ",-1),je=e("span",null," Delete ",-1),Me={key:0},Fe=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ke=[Fe],ot={__name:"Index",props:{customers:Object,tags:Object},setup(l){const T=l,s=c({name:"",customers:[],sortKey:"",sortBy:!0,numberPerPage:100}),p=c(!1),v=c(),y=c(""),b=c([]),V=c([]);q(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=b.value[0],V.value=T.customers.data.map(r=>({id:r.id,code:r.code,name:r.name,is_active:r.is_active}))});function j(){y.value="create",v.value=null,p.value=!0}function M(r){!confirm("Are you sure to delete "+r.name+"?")||P.Inertia.delete("/tags/"+r.id)}function F(r){y.value="update",v.value=r,p.value=!0}function w(){P.Inertia.get("/tags",{...s.value,customers:s.value.customers.map(r=>r.id),numberPerPage:s.value.numberPerPage.id},{preserveState:!0,replace:!0})}function K(){P.Inertia.get("/tags")}function D(r){s.value.sortKey=r,s.value.sortBy=!s.value.sortBy,w()}function O(){p.value=!1}return(r,o)=>(i(),m(C,null,[t(f(z),{title:"Tags"}),t(U,null,{header:n(()=>[Y]),default:n(()=>{var B,S;return[e("div",Z,[e("div",ee,[e("div",te,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=a=>j())},{default:n(()=>[t(f(G),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(E,{placeholderStr:"Name",modelValue:s.value.name,"onUpdate:modelValue":o[1]||(o[1]=a=>s.value.name=a)},{default:n(()=>[ae]),_:1},8,["modelValue"]),e("div",ne,[le,t(L,{modelValue:s.value.customers,"onUpdate:modelValue":o[2]||(o[2]=a=>s.value.customers=a),options:V.value,valueProp:"id",label:"code",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",re,[e("div",ie,[e("div",de,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=a=>w())},{default:n(()=>[t(f(H),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[4]||(o[4]=a=>K())},{default:n(()=>[t(f(Q),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",fe,[ge,e("span",pe,d((B=l.tags.meta.from)!=null?B:0),1),xe,e("span",_e,d((S=l.tags.meta.to)!=null?S:0),1),he,e("span",ve,d(l.tags.meta.total),1),ye]),t(L,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":o[5]||(o[5]=a=>s.value.numberPerPage=a),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",be,[e("div",we,[e("div",ke,[e("table",Ce,[e("thead",$e,[e("tr",Pe,[t(k,null,{default:n(()=>[Ve]),_:1}),t(J,{modelName:"name",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:o[6]||(o[6]=a=>D("name"))},{default:n(()=>[Be]),_:1},8,["sortKey","sortBy"]),t(k,null,{default:n(()=>[Se]),_:1}),t(k)])]),e("tbody",Le,[(i(!0),m(C,null,N(l.tags.data,(a,x)=>(i(),m("tr",{key:a.id,class:"divide-x divide-gray-200"},[t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[g(d(l.tags.meta.from+x),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[g(d(a.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-left"},{default:n(()=>[(i(!0),m(C,null,N(a.tagBindings.filter(function(u){return u.is_active}),u=>(i(),m("span",Ne,d(u.code)+", "+d(u.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:x,totalLength:l.tags.length,inputClass:"text-center"},{default:n(()=>[e("div",Ie,[t(_,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:u=>F(a)},{default:n(()=>[t(f(W),{class:"w-4 h-4"}),Te]),_:2},1032,["onClick"]),t(_,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:u=>M(a)},{default:n(()=>[t(f(X),{class:"w-4 h-4"}),je]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),l.tags.data.length?$("",!0):(i(),m("tr",Me,Ke))])]),l.tags.data.length?(i(),I(R,{key:0,links:l.tags.links,meta:l.tags.meta},null,8,["links","meta"])):$("",!0)])])])]),p.value?(i(),I(A,{key:0,tag:v.value,type:y.value,showModal:p.value,onModalClose:O},null,8,["tag","type","showModal"])):$("",!0)]}),_:1})],64))}};export{ot as default};