import{_ as T}from"./Authenticated.90795793.js";import{_ as f}from"./Button.d69eb374.js";import O from"./Form.b3fa34b5.js";import{r as U,a as A,T as w,_ as E,b as H,c as x}from"./TableHeadSort.3fbd8d70.js";import{_ as R}from"./SearchInput.40d238c9.js";import{_ as B}from"./MultiSelect.fbbfc793.js";import{i as d,j as J,o as c,g as _,a as t,b as u,w as a,F as S,H as q,d as e,t as m,m as z,p as k,c as G,f as i,J as C}from"./app.7b13628b.js";import{r as Q,a as W}from"./PlusIcon.8403408b.js";import{r as X}from"./TrashIcon.10711b03.js";import"./open-closed.8ceffd08.js";import"./use-resolve-button-type.1cfd894d.js";import"./RectangleStackIcon.110c11e3.js";import"./Modal.c96b1c99.js";import"./FormTextarea.cd2ecb49.js";import"./ArrowUturnLeftIcon.0837d764.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Y=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Category Groups) ",-1),Z={class:"m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=i(" Name "),ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Categories ",-1),le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},ie={class:"flex space-x-1"},de=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},me={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),pe={class:"font-medium"},fe=e("span",null,"to",-1),xe={class:"font-medium"},_e=e("span",null,"of",-1),he={class:"font-medium"},ve=e("span",null,"results",-1),ye={class:"mt-6 flex flex-col"},be={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},we={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ke={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ce={class:"bg-gray-100"},$e={class:"divide-x divide-gray-200"},Pe=i(" # "),Ve=i(" Name "),Be=i(" Desc "),Se={class:"bg-white"},Ge={class:"flex justify-center space-x-1"},Le=e("span",null," Edit ",-1),Ne=e("span",null," Delete ",-1),Ie={key:0},je=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Me=[je],Ye={__name:"Index",props:{categories:Object,categoryGroups:Object},setup(n){const L=n,l=d({name:"",categories:[],sortKey:"",sortBy:!0,numberPerPage:100}),g=d(!1),h=d(),v=d(""),y=d([]),$=d([]);J(()=>{$.value=L.categories.data,y.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=y.value[0]});function N(){v.value="create",h.value=null,g.value=!0}function I(r){!confirm("Are you sure to delete "+r.name+"?")||C.Inertia.delete("/category-groups/"+r.id)}function j(r){v.value="update",h.value=r,g.value=!0}function b(){C.Inertia.get("/category-groups",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function M(){C.Inertia.get("/category-groups")}function D(r){l.value.sortKey=r,l.value.sortBy=!l.value.sortBy,b()}function F(){g.value=!1}return(r,s)=>(c(),_(S,null,[t(u(q),{title:"Category Groups"}),t(T,null,{header:a(()=>[Y]),default:a(()=>{var P,V;return[e("div",Z,[e("div",ee,[e("div",te,[t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=o=>N())},{default:a(()=>[t(u(Q),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(R,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":s[1]||(s[1]=o=>l.value.name=o)},{default:a(()=>[ae]),_:1},8,["modelValue"]),e("div",null,[ne,t(B,{modelValue:l.value.categories,"onUpdate:modelValue":s[2]||(s[2]=o=>l.value.categories=o),options:$.value,valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",le,[e("div",re,[e("div",ie,[t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[3]||(s[3]=o=>b())},{default:a(()=>[t(u(U),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1}),t(f,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[4]||(s[4]=o=>M())},{default:a(()=>[t(u(A),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",ue,[e("p",me,[ge,e("span",pe,m((P=n.categoryGroups.meta.from)!=null?P:0),1),fe,e("span",xe,m((V=n.categoryGroups.meta.to)!=null?V:0),1),_e,e("span",he,m(n.categoryGroups.meta.total),1),ve]),t(B,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":s[5]||(s[5]=o=>l.value.numberPerPage=o),options:y.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:b},null,8,["modelValue","options"])])])]),e("div",ye,[e("div",be,[e("div",we,[e("table",ke,[e("thead",Ce,[e("tr",$e,[t(w,null,{default:a(()=>[Pe]),_:1}),t(E,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:s[6]||(s[6]=o=>D("name"))},{default:a(()=>[Ve]),_:1},8,["sortKey","sortBy"]),t(w,null,{default:a(()=>[Be]),_:1}),t(w)])]),e("tbody",Se,[(c(!0),_(S,null,z(n.categoryGroups.data,(o,p)=>(c(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:p,totalLength:n.categoryGroups.length,inputClass:"text-center"},{default:a(()=>[i(m(n.categoryGroups.meta.from+p),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:n.categoryGroups.length,inputClass:"text-left"},{default:a(()=>[i(m(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:n.categoryGroups.length,inputClass:"text-left whitespace-pre-wrap"},{default:a(()=>[i(m(o.desc),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:p,totalLength:n.categoryGroups.length,inputClass:"text-center"},{default:a(()=>[e("div",Ge,[t(f,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:K=>j(o)},{default:a(()=>[t(u(W),{class:"w-4 h-4"}),Le]),_:2},1032,["onClick"]),t(f,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:K=>I(o)},{default:a(()=>[t(u(X),{class:"w-4 h-4"}),Ne]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.categoryGroups.data.length?k("",!0):(c(),_("tr",Ie,Me))])]),n.categoryGroups.data.length?(c(),G(H,{key:0,links:n.categoryGroups.links,meta:n.categoryGroups.meta},null,8,["links","meta"])):k("",!0)])])])]),g.value?(c(),G(O,{key:0,categoryGroup:h.value,type:v.value,showModal:g.value,onModalClose:F},null,8,["categoryGroup","type","showModal"])):k("",!0)]}),_:1})],64))}};export{Ye as default};
