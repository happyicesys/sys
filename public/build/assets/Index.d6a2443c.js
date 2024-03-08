import{_ as K}from"./Authenticated.7e90e6af.js";import{_ as p}from"./Button.4631b684.js";import U from"./Form.eac4d15e.js";import{_ as A}from"./Paginator.f614a30a.js";import{_ as E,r as T}from"./SearchInput.dcca857e.js";import{_ as G}from"./MultiSelect.0c44488a.js";import{_,a as x}from"./TableData.e3cd56cb.js";import{_ as R}from"./TableHeadSort.6b73435e.js";import{g as c,h as Z,f as h,a as t,u as m,w as o,F as L,o as g,Z as q,b as e,d as r,t as d,k as z,l as C,c as S,O as k}from"./app.c4e47028.js";import{r as H}from"./PlusIcon.fa20410d.js";import{r as J}from"./BackspaceIcon.dae45b54.js";import{r as Q}from"./PencilSquareIcon.ac8e2982.js";import{r as W}from"./TrashIcon.eeea9cb1.js";import"./keyboard.58689cfa.js";import"./use-resolve-button-type.ef81a21b.js";import"./RectangleStackIcon.b91bd271.js";import"./FormInput.2fd92b17.js";import"./FormTextarea.c34ee5c9.js";import"./Modal.9639af57.js";import"./disposables.be045d92.js";import"./ArrowUturnLeftIcon.8d3b347e.js";import"./CheckCircleIcon.3fae9752.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";const X=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Data Settings (Customer Categories) ",-1),Y={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ee={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},te={class:"flex justify-end"},se=e("span",null," Create ",-1),oe={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ae=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category Group ",-1),le={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ne={class:"mt-3"},re={class:"flex space-x-1"},ie=e("span",null," Search ",-1),de=e("span",null," Reset ",-1),ue={class:"flex flex-col space-y-2"},ce={class:"text-sm text-gray-700 leading-5 flex space-x-1"},me=e("span",null,"Showing",-1),ge={class:"font-medium"},fe=e("span",null,"to",-1),pe={class:"font-medium"},xe=e("span",null,"of",-1),_e={class:"font-medium"},he=e("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},ye={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ce={class:"bg-gray-100"},ke={class:"divide-x divide-gray-200"},$e={class:"bg-white"},Pe={class:"flex justify-center space-x-1"},Ve=e("span",null," Edit ",-1),Be=e("span",null," Delete ",-1),Ge={key:0},Le=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Se=[Le],tt={__name:"Index",props:{categories:Object,categoryGroups:Object},setup(a){const $=a,n=c({name:"",categoryGroups:[],sortKey:"",sortBy:!0,numberPerPage:100}),f=c(!1),v=c(),y=c(""),b=c([]),P=c([]);Z(()=>{P.value=$.categoryGroups.data,b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=b.value[0]});function N(){y.value="create",v.value=null,f.value=!0}function I(i){!confirm("Are you sure to delete "+i.name+"?")||k.delete("/categories/"+i.id)}function M(i){y.value="update",v.value=i,f.value=!0}function w(){k.get("/categories",{...n.value,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function O(){k.get("/categories")}function j(i){n.value.sortKey=i,n.value.sortBy=!n.value.sortBy,w()}function D(){f.value=!1}return(i,l)=>(g(),h(L,null,[t(m(q),{title:"Categories"}),t(K,null,{header:o(()=>[X]),default:o(()=>{var V,B;return[e("div",Y,[e("div",ee,[e("div",te,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=s=>N())},{default:o(()=>[t(m(H),{class:"h-4 w-4","aria-hidden":"true"}),se]),_:1})]),e("div",oe,[t(E,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":l[1]||(l[1]=s=>n.value.name=s)},{default:o(()=>[r(" Name ")]),_:1},8,["modelValue"]),e("div",null,[ae,t(G,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":l[2]||(l[2]=s=>n.value.categoryGroups=s),options:P.value,valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),e("div",le,[e("div",ne,[e("div",re,[t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[3]||(l[3]=s=>w())},{default:o(()=>[t(m(T),{class:"h-4 w-4","aria-hidden":"true"}),ie]),_:1}),t(p,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[4]||(l[4]=s=>O())},{default:o(()=>[t(m(J),{class:"h-4 w-4","aria-hidden":"true"}),de]),_:1})])]),e("div",ue,[e("p",ce,[me,e("span",ge,d((V=a.categories.meta.from)!=null?V:0),1),fe,e("span",pe,d((B=a.categories.meta.to)!=null?B:0),1),xe,e("span",_e,d(a.categories.meta.total),1),he]),t(G,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":l[5]||(l[5]=s=>n.value.numberPerPage=s),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",ve,[e("div",ye,[e("div",be,[e("table",we,[e("thead",Ce,[e("tr",ke,[t(_,null,{default:o(()=>[r(" # ")]),_:1}),t(R,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:l[6]||(l[6]=s=>j("name"))},{default:o(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(_,null,{default:o(()=>[r(" Desc ")]),_:1}),t(_,null,{default:o(()=>[r(" Category Group ")]),_:1}),t(_)])]),e("tbody",$e,[(g(!0),h(L,null,z(a.categories.data,(s,u)=>(g(),h("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(x,{currentIndex:u,totalLength:a.categories.length,inputClass:"text-center"},{default:o(()=>[r(d(a.categories.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:u,totalLength:a.categories.length,inputClass:"text-left"},{default:o(()=>[r(d(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:u,totalLength:a.categories.length,inputClass:"text-left whitespace-pre-wrap"},{default:o(()=>[r(d(s.desc),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:u,totalLength:a.categories.length,inputClass:"text-left"},{default:o(()=>[r(d(s.category_group_id?s.category_group_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(x,{currentIndex:u,totalLength:a.categories.length,inputClass:"text-center"},{default:o(()=>[e("div",Pe,[t(p,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:F=>M(s)},{default:o(()=>[t(m(Q),{class:"w-4 h-4"}),Ve]),_:2},1032,["onClick"]),t(p,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:F=>I(s)},{default:o(()=>[t(m(W),{class:"w-4 h-4"}),Be]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.categories.data.length?C("",!0):(g(),h("tr",Ge,Se))])]),a.categories.data.length?(g(),S(A,{key:0,links:a.categories.links,meta:a.categories.meta},null,8,["links","meta"])):C("",!0)])])])]),f.value?(g(),S(U,{key:0,category:v.value,categoryGroups:$.categoryGroups,type:y.value,showModal:f.value,onModalClose:D},null,8,["category","categoryGroups","type","showModal"])):C("",!0)]}),_:1})],64))}};export{tt as default};
